<?php

declare(strict_types=1);

namespace Plugin\WechatService\Service;

use Library\Constants\Status;
use Library\Exception\ErrorResponseException;
use Plugin\WechatService\Model\WechatServiceAuth;
use Plugin\WechatService\Support\HyperfCacheStore;
use We\Client as WechatSdkClient;

final class WechatServiceRpcService
{
    public function __construct(
        private WechatServiceGatewayService $credentials,
        private WechatServiceConfigService $configs,
        private WechatServiceAuthService $authorizers,
    ) {}

    /**
     * 处理内部 JSON-RPC 2.0 请求，只开放白名单方法，避免远端任意调用 SDK 类。
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function handle(string $token, array $payload): array
    {
        $method = (string)($payload['method'] ?? '');
        $id = $payload['id'] ?? null;

        try {
            $identity = $this->credentials->verifyToken($token);
            $result = match ($method) {
                'wechat.platform.request' => $this->officialRequest($identity['appid'], (array)($payload['params'] ?? [])),
                'authorizer.info' => $this->authorizerInfo($identity['appid']),
                default => throw new ErrorResponseException('JSON-RPC 方法不支持'),
            };

            return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
        } catch (\Throwable $throwable) {
            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => [
                    'code' => (int)($throwable->getCode() ?: 500),
                    'message' => $throwable->getMessage(),
                ],
            ];
        }
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    private function officialRequest(string $appid, array $params): array
    {
        $this->requireEnabledAuthorizer($appid);

        $config = $this->configs->sdkConfig();
        $client = new WechatSdkClient(cache: new HyperfCacheStore(), authorizers: $this->authorizers);
        $uri = $this->normalizeWechatApiPath((string)($params['uri'] ?? ''));
        $http = strtoupper(trim((string)($params['http_method'] ?? 'GET')) ?: 'GET');
        $isMessageCrypto = in_array($uri, ['decrypt_message', 'encrypt_message'], true);

        // JSON-RPC 的 params.payload 作为请求 body，params.query 作为 URL query；
        // 代调用凭据由服务端重新注入，客户端不能覆盖。Guzzle 原生 options 不接受远端透传，
        // 避免调用方通过 sink/proxy/debug 等选项影响服务端网络或文件系统。
        $payload = is_array($params['payload'] ?? null) ? $params['payload'] : [];
        $query = is_array($params['query'] ?? null) ? $params['query'] : [];
        $options = ['query' => $query];
        if (!$isMessageCrypto) {
            // 新版 SDK 在服务平台客户端内统一刷新 authorizer_access_token；网关侧只注入受信任的授权方上下文。
            $options['authorizer_appid'] = $appid;
            $options['component_access_token'] = $this->configs->componentAccessToken();
            $this->authorizers->incrementTotal($appid);
        }

        $service = $client->wechatService($config);

        return match ($http) {
            'GET' => $service->get($uri, $query, $options),
            'POST' => $service->post($uri, $payload, $options),
            default => $service->call($uri, $payload, $http, $options),
        };
    }

    /**
     * @return array<string,mixed>
     */
    private function authorizerInfo(string $appid): array
    {
        $authorizer = $this->requireEnabledAuthorizer($appid);

        return $authorizer->toArray();
    }

    private function normalizeWechatApiPath(string $uri): string
    {
        $uri = trim($uri);
        // JSON-RPC 网关只允许代调用微信官方相对 API path，拒绝绝对 URL 和内嵌 query/fragment，
        // query 必须走 params.query 白名单字段，避免调用方把 access_token 等敏感参数塞进路径。
        if ($uri === '' || str_starts_with($uri, '//') || str_contains($uri, '?') || str_contains($uri, '#') || preg_match('#^[a-z][a-z0-9+.-]*:#i', $uri) === 1) {
            throw new ErrorResponseException('微信接口路径无效');
        }

        return ltrim($uri, '/');
    }

    private function requireEnabledAuthorizer(string $appid): WechatServiceAuth
    {
        $authorizer = $this->authorizers->findByAppid($appid);
        if (!$authorizer instanceof WechatServiceAuth) {
            throw new ErrorResponseException('授权账号不存在');
        }
        if (!Status::isEnabled((int)$authorizer->status)) {
            throw new ErrorResponseException('授权账号不可用');
        }

        return $authorizer;
    }
}
