<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Plugin\Website\Service;

use Hyperf\HttpServer\Contract\RequestInterface;
use Library\Constants\DataField;
use Library\Constants\Status;
use Library\Exception\NotAllowResponseException;
use Library\Exception\UnauthorizedResponseException;
use Plugin\Website\Mapper\WebsiteAppMapper;
use Plugin\Website\Model\WebsiteApp;
use Plugin\Website\Model\WebsiteSite;
use Plugin\Website\Support\Secret;
use Plugin\Website\Support\WebsiteOpenApiContext;
use Plugin\Website\Support\WebsiteOpenApiScope;
use Plugin\Website\Support\WebsiteOpenApiSignature;

/**
 * 官网开放接口签名认证服务。
 *
 * 该服务不依赖后台登录态；所有边界都来自 AppID 对应的应用记录，并在验签通过后绑定唯一站点。
 */
final class WebsiteOpenApiAuthenticator
{
    private const TIMESTAMP_TOLERANCE = 300;

    public function __construct(
        private WebsiteAppMapper $apps
    ) {}

    public function assertScope(WebsiteOpenApiContext $context, string $scope): void
    {
        if (!WebsiteOpenApiScope::allows($context->app->scopes, $scope)) {
            throw new NotAllowResponseException('开放接口权限不足');
        }
    }

    public function authenticate(RequestInterface $request, string $scope, string $ip): WebsiteOpenApiContext
    {
        $credentials = $this->resolveCredentials($request);
        $appId = $credentials['app_id'];
        $timestamp = $credentials['timestamp'];
        $nonce = $credentials['nonce'];
        $sign = $credentials['signature'];
        if ($appId === '' || $timestamp === '' || $nonce === '' || $sign === '') {
            throw new UnauthorizedResponseException('开放接口签名头缺失');
        }
        if (!ctype_digit($timestamp) || abs(time() - (int)$timestamp) > self::TIMESTAMP_TOLERANCE) {
            throw new UnauthorizedResponseException('开放接口签名已过期');
        }
        if (preg_match('/^[A-Za-z0-9._-]{8,64}$/', $nonce) !== 1) {
            throw new UnauthorizedResponseException('开放接口随机串格式错误');
        }
        if (preg_match('/^[a-f0-9]{64}$/', $sign) !== 1) {
            throw new UnauthorizedResponseException('开放接口签名格式错误');
        }

        $app = $this->apps->findForOpenApi($appId);
        if (!$app instanceof WebsiteApp) {
            throw new UnauthorizedResponseException('开放接口应用不存在');
        }
        if ((int)$app->status !== Status::ENABLED) {
            throw new NotAllowResponseException('开放接口应用已禁用');
        }

        $appKey = Secret::decrypt((string)$app->getRawOriginal('app_key'));
        if ($appKey === '') {
            throw new NotAllowResponseException('开放接口密钥不可用');
        }

        $expected = WebsiteOpenApiSignature::sign(
            $appKey,
            $request->getMethod(),
            $request->getUri()->getPath(),
            $request->getQueryParams(),
            (string)$request->getBody(),
            $timestamp,
            $nonce
        );
        if (!hash_equals($expected, $sign)) {
            throw new UnauthorizedResponseException('开放接口签名错误');
        }
        $this->assertNonceUnused((string)$app->app_id, $nonce);

        if (!WebsiteOpenApiScope::allows($app->scopes, $scope)) {
            throw new NotAllowResponseException('开放接口权限不足');
        }
        if (!$this->isIpAllowed($ip, $app->ip_whitelist)) {
            throw new NotAllowResponseException('当前 IP 不允许调用开放接口');
        }
        $this->assertRateLimit($app);

        $site = WebsiteSite::query()
            ->withoutGlobalScope(DataField::TENANT)
            ->where('tenant_id', (int)$app->tenant_id)
            ->where('id', (int)$app->site_id)
            ->where('status', Status::ENABLED)
            ->first();
        if (!$site instanceof WebsiteSite) {
            throw new NotAllowResponseException('开放接口绑定站点不可用');
        }

        WebsiteApp::query()
            ->withoutGlobalScope(DataField::TENANT)
            ->where('id', (int)$app->id)
            ->update(['last_used_at' => date('Y-m-d H:i:s'), 'last_used_ip' => $ip]);

        return new WebsiteOpenApiContext($app, $site, $scope, $ip);
    }

    /**
     * 开放接口优先使用标准 Authorization 认证头：
     * Authorization: Website-HMAC appid="...", timestamp="...", nonce="...", signature="..."
     *
     * 历史 X-Website-* 头保留兼容，避免已接入的服务端调用在升级时立即失效。
     *
     * @return array{app_id: string, timestamp: string, nonce: string, signature: string}
     */
    private function resolveCredentials(RequestInterface $request): array
    {
        $authorization = trim($request->getHeaderLine('Authorization'));
        if ($authorization !== '') {
            if (!preg_match('/^\s*' . preg_quote(WebsiteOpenApiSignature::AUTH_SCHEME, '/') . '\s+/i', $authorization)) {
                $legacy = $this->legacyCredentials($request);
                if (implode('', $legacy) !== '') {
                    return $legacy;
                }
            }

            return $this->parseAuthorization($authorization);
        }

        return $this->legacyCredentials($request);
    }

    /**
     * @return array{app_id: string, timestamp: string, nonce: string, signature: string}
     */
    private function legacyCredentials(RequestInterface $request): array
    {
        return [
            'app_id' => strtolower(trim($request->getHeaderLine('X-Website-Appid'))),
            'timestamp' => trim($request->getHeaderLine('X-Website-Timestamp')),
            'nonce' => trim($request->getHeaderLine('X-Website-Nonce')),
            'signature' => strtolower(trim($request->getHeaderLine('X-Website-Sign'))),
        ];
    }

    /**
     * 按 RFC Auth Scheme + auth-param 风格解析 Authorization，参数名大小写不敏感。
     *
     * @return array{app_id: string, timestamp: string, nonce: string, signature: string}
     */
    private function parseAuthorization(string $authorization): array
    {
        $scheme = preg_quote(WebsiteOpenApiSignature::AUTH_SCHEME, '/');
        if (preg_match('/^\s*' . $scheme . '\s+(.+)\s*$/i', $authorization, $match) !== 1) {
            throw new UnauthorizedResponseException('开放接口 Authorization 格式错误');
        }

        $params = [];
        if (preg_match_all('/([A-Za-z][A-Za-z0-9_-]*)\s*=\s*(?:"((?:[^"\\\\]|\\\\.)*)"|([^,\s]+))/u', $match[1], $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $item) {
                $key = strtolower(str_replace('_', '', $item[1]));
                $value = $item[2] !== '' ? stripcslashes($item[2]) : ($item[3] ?? '');
                $params[$key] = trim($value);
            }
        }

        return [
            'app_id' => strtolower($params['appid'] ?? ''),
            'timestamp' => $params['timestamp'] ?? '',
            'nonce' => $params['nonce'] ?? '',
            'signature' => strtolower($params['signature'] ?? $params['sign'] ?? ''),
        ];
    }

    private function assertNonceUnused(string $appId, string $nonce): void
    {
        $key = sprintf('website:openapi:nonce:%s:%s', $appId, hash('sha256', $nonce));
        if (_cache($key) !== null) {
            throw new UnauthorizedResponseException('开放接口随机串已使用');
        }

        _cache($key, 1, self::TIMESTAMP_TOLERANCE);
    }

    private function assertRateLimit(WebsiteApp $app): void
    {
        $limit = (int)$app->rate_limit;
        if ($limit <= 0) {
            return;
        }

        $key = sprintf('website:openapi:rate:%s:%s', (string)$app->app_id, date('YmdHi'));
        $count = (int)(_cache($key) ?? 0) + 1;
        _cache($key, $count, 70);
        if ($count > $limit) {
            throw new NotAllowResponseException('开放接口调用过于频繁');
        }
    }

    /**
     * @param array<int, string> $whitelist
     */
    private function isIpAllowed(string $ip, array $whitelist): bool
    {
        $ip = trim($ip);
        if ($whitelist === [] || in_array('*', $whitelist, true)) {
            return true;
        }
        if ($ip === '') {
            return false;
        }
        foreach ($whitelist as $rule) {
            $rule = trim((string)$rule);
            if ($rule === $ip) {
                return true;
            }
            if ($this->ipv4CidrContains($ip, $rule)) {
                return true;
            }
        }

        return false;
    }

    private function ipv4CidrContains(string $ip, string $rule): bool
    {
        if (preg_match('/^([0-9]{1,3}(?:\.[0-9]{1,3}){3})\/(\d{1,2})$/', $rule, $matches) !== 1) {
            return false;
        }
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || !filter_var($matches[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        $prefix = (int)$matches[2];
        if ($prefix < 0 || $prefix > 32) {
            return false;
        }
        $mask = $prefix === 0 ? 0 : (-1 << (32 - $prefix));

        return ((int)ip2long($ip) & $mask) === ((int)ip2long($matches[1]) & $mask);
    }
}
