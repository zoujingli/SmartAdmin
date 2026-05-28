<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Plugin\WechatClient\Service;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Library\Constants\Status;
use Library\CoreService;
use Library\Exception\ErrorResponseException;
use Library\Helper\CoderHelper;
use Plugin\WechatClient\Mapper\WechatClientAccountMapper;
use Plugin\WechatClient\Model\WechatClientAccount;
use Plugin\WechatClient\Support\HyperfCacheStore;
use Plugin\WechatClient\Support\Secret;
use We\Client as WechatSdkClient;
use We\Config\WechatPlatformConfig;
use We\Config\WechatWxappConfig;
use We\Support\MessageCrypto;
use We\Support\Signature;

/**
 * 微信接口账号服务。
 *
 * 负责公众号/小程序账号配置、敏感字段加密、直连微信官方接口和开放平台网关代调用。
 */
final class WechatClientAccountService extends CoreService
{
    /**
     * 需要加密保存的账号敏感字段。
     */
    private const SECRET_FIELDS = ['appsecret', 'token', 'encodingaeskey', 'access_token', 'refresh_token'];

    public function __construct(
        protected WechatClientAccountMapper $mapper
    ) {}

    /**
     * 统一公众号通用调用入口：按 URI + 参数请求。
     *
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public function officialRequest(int|WechatClientAccount $account, string $uriOrPath, array $payload = [], string $httpMethod = 'POST', array $options = []): array
    {
        $account = $this->requireAccount($account);
        if ((int)$account->service_mode === 1) {
            return $this->gatewayRequest($account, $uriOrPath, $payload, $httpMethod, $options);
        }

        $uri = ltrim($uriOrPath, '/');
        if (in_array($uri, ['decrypt_message', 'encrypt_message'], true)) {
            // 消息加解密只依赖 Token、EncodingAESKey 和 AppID；不强制要求 AppSecret，避免仅配置回调的账号无法处理安全模式推送。
            return $this->messageCryptoRequest($account, $uri, $payload);
        }

        $storageScope = 't' . (string)$account->tenant_id . ':a' . (string)$account->id;
        $appSecret = Secret::decrypt((string)$account->appsecret);
        $sdk = $this->sdkClient();
        if ((string)$account->account_type === 'mini_program') {
            // 新版 WechatDeveloper 拆分了公众号与小程序通道；小程序接口走 wxapp 通道，只依赖 AppSecret 和独立缓存桶。
            $platform = $sdk->wechatWxapp(new WechatWxappConfig((string)$account->appid, $appSecret, $storageScope));
        } else {
            // 公众号普通接口仍走 platform 通道，access_token 缓存与消息安全参数共享同一账号隔离范围。
            $platform = $sdk->wechatPlatform(new WechatPlatformConfig(
                (string)$account->appid,
                $appSecret,
                Secret::decrypt((string)$account->token),
                Secret::decrypt((string)$account->encodingaeskey),
                $storageScope,
            ));
        }
        $method = strtoupper(trim($httpMethod) === '' ? 'POST' : $httpMethod);

        return match ($method) {
            'GET' => $platform->get($uriOrPath, $payload, $options),
            'POST' => $platform->post($uriOrPath, $payload, $options),
            default => $platform->call($uriOrPath, $payload, $method, $options),
        };
    }

    /**
     * 获取并校验可用的微信接口账号。
     */
    public function requireAccount(int|WechatClientAccount $account): WechatClientAccount
    {
        if ($account instanceof WechatClientAccount) {
            if (!Status::isEnabled((int)$account->status)) {
                throw new ErrorResponseException('微信接口账号不可用');
            }

            return $account;
        }
        $model = $this->mapper->read($account);
        if (!$model instanceof WechatClientAccount || !Status::isEnabled((int)$model->status)) {
            throw new ErrorResponseException('微信接口账号不可用');
        }

        return $model;
    }

    /**
     * 回调场景按 AppID 查找账号。
     *
     * 微信推送没有后台登录态，需要跨租户查询账号后再恢复租户上下文。
     */
    public function findByAppidForCallback(string $appid): WechatClientAccount
    {
        $account = $this->mapper->findByAppid($appid);
        if (!$account instanceof WechatClientAccount || !Status::isEnabled((int)$account->status)) {
            throw new ErrorResponseException('微信接口账号不可用');
        }

        return $account;
    }

    /**
     * 校验公众号明文回调签名。
     *
     * 微信公网回调没有登录态，明文模式必须使用账号 Token 对 signature/timestamp/nonce 做 SHA1 校验，
     * 避免伪造关注、消息、菜单事件进入后续业务分发。
     *
     * @param array<string,mixed> $query
     */
    public function assertPlainCallbackSignature(WechatClientAccount $account, array $query): void
    {
        $token = Secret::decrypt((string)$account->token);
        if ($token === '') {
            throw new ErrorResponseException('微信回调 Token 未配置');
        }

        Signature::assertSha1((string)($query['signature'] ?? ''), [
            $token,
            (string)($query['timestamp'] ?? ''),
            (string)($query['nonce'] ?? ''),
        ]);
    }

    /**
     * 保存账号前统一校验字段，并对敏感配置加密。
     *
     * 更新时如果前端传回掩码值，表示保持原密文，不重新加密。
     */
    protected function filterData(array &$data, array $exists = []): array
    {
        $secrets = [];
        foreach (self::SECRET_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $secrets[$field] = $data[$field];
            }
        }
        $extra = $data['extra'] ?? null;
        $rules = [
            'tenant_id.integer' => '租户 ID 必须为数字',
            'tenant_id.min:0' => '租户 ID 不能小于 0',
            'appid.filled' => '微信 AppID 不能为空',
            'appid.max:64' => '微信 AppID 最多 64 位',
            'name.filled' => '账号名称不能为空',
            'name.max:120' => '账号名称最多 120 位',
            'account_type.max:30' => '账号类型最多 30 位',
            'service_mode.integer' => '接入模式必须为数字',
            'service_mode.in:0,1' => '接入模式错误',
            'status.integer' => '状态值必须为数字',
            'status.in:1,0' => '状态值错误',
        ];
        if ($exists === []) {
            $rules['tenant_id.default'] = tenant_id();
            $rules['appid.required'] = '微信 AppID 不能为空';
            $rules['name.required'] = '账号名称不能为空';
            $rules['account_type.default'] = 'official_account';
            $rules['service_mode.default'] = 0;
            $rules['status.default'] = Status::ENABLED;
        }

        $data = _vali($rules, $data);
        if (array_key_exists('appid', $data) && $this->mapper->existsByAppid((string)$data['appid'], (int)($exists['id'] ?? 0))) {
            throw new ErrorResponseException('微信 AppID 已存在');
        }
        if ($extra !== null) {
            $data['extra'] = $this->filterExtra($extra, $exists);
        }
        foreach (['tenant_id', 'service_mode', 'expires_at', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = (int)$data[$field];
            }
        }
        foreach ($secrets as $field => $value) {
            if (Secret::isMask($value)) {
                continue;
            }
            $data[$field] = Secret::encrypt((string)$value);
        }

        return $data;
    }

    /**
     * 处理微信消息安全模式伪路径。
     *
     * SDK 新版将消息加解密能力放在协议层客户端内，但客户端配置会校验 AppSecret；
     * 公众号服务器回调只需要 Token、EncodingAESKey 与 AppID，因此这里直接复用 SDK 的 MessageCrypto 支持类。
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function messageCryptoRequest(WechatClientAccount $account, string $uri, array $payload): array
    {
        $crypto = new MessageCrypto(
            Secret::decrypt((string)$account->token),
            Secret::decrypt((string)$account->encodingaeskey),
            (string)$account->appid,
        );
        if ($uri === 'decrypt_message') {
            return $crypto->decryptMessage(
                (string)($payload['body'] ?? ''),
                (string)($payload['msg_signature'] ?? ''),
                (string)($payload['timestamp'] ?? ''),
                (string)($payload['nonce'] ?? ''),
            );
        }

        return [
            'xml' => $crypto->encryptMessage(
                (string)($payload['body'] ?? ''),
                (string)($payload['timestamp'] ?? time()),
                (string)($payload['nonce'] ?? ''),
            ),
        ];
    }

    /**
     * 开放平台模式通过内部 JSON-RPC 网关代调用授权账号，客户端只持有网关凭据。
     *
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    private function gatewayRequest(
        WechatClientAccount $account,
        string $uriOrPath,
        array $payload,
        string $httpMethod,
        array $options,
    ): array {
        $extra = $this->rawExtra($account);
        $rpcUrl = trim((string)($extra['gateway_url'] ?? ''));
        $clientKey = trim((string)($extra['gateway_client_key'] ?? ''));
        $clientSecret = Secret::decrypt((string)($extra['gateway_client_secret'] ?? ''));
        if ($rpcUrl === '' || $clientKey === '' || $clientSecret === '') {
            throw new ErrorResponseException('开放平台网关配置不完整');
        }
        $this->assertGatewayUrl($rpcUrl);

        $method = strtoupper(trim($httpMethod) === '' ? 'POST' : $httpMethod);
        $id = CoderHelper::uuid();
        // 代调用身份只能由 WechatService 注入，客户端侧传入的同名参数直接丢弃。
        unset($options['component_access_token'], $options['authorizer_appid']);
        $query = $method === 'GET' ? $payload : (is_array($options['query'] ?? null) ? $options['query'] : []);
        $body = $method === 'GET' ? [] : $payload;
        $request = [
            'jsonrpc' => '2.0',
            'id' => $id,
            'method' => 'wechat.platform.request',
            'params' => [
                'uri' => $uriOrPath,
                'http_method' => $method,
                'query' => $query,
                'payload' => $body,
                'options' => $options,
            ],
        ];

        try {
            $response = (new GuzzleClient(['timeout' => 20.0]))->post($rpcUrl, [
                'allow_redirects' => false,
                'query' => ['token' => $this->gatewayToken((string)$account->appid, $clientKey, $clientSecret)],
                'json' => $request,
            ]);
        } catch (GuzzleException $exception) {
            throw new ErrorResponseException('开放平台网关请求失败：' . $exception->getMessage());
        }

        $result = json_decode((string)$response->getBody(), true);
        if (!is_array($result)) {
            throw new ErrorResponseException('开放平台网关响应无效');
        }
        if (is_array($result['error'] ?? null)) {
            throw new ErrorResponseException((string)($result['error']['message'] ?? '开放平台网关调用失败'));
        }

        return is_array($result['result'] ?? null) ? $result['result'] : [];
    }

    /**
     * 生成开放平台网关请求 token，用于网关侧校验调用方身份。
     */
    private function gatewayToken(string $appid, string $clientKey, string $clientSecret): string
    {
        $time = time();
        $nonce = CoderHelper::genRandCode(16, 3);
        $class = 'wechat-client';
        $payload = [
            'class' => $class,
            'appid' => $appid,
            'time' => $time,
            'nonce' => $nonce,
            'key' => $clientKey,
            'sign' => hash_hmac('sha256', implode('|', [$class, $appid, $time, $nonce, $clientKey]), $clientSecret),
        ];

        return CoderHelper::ensafe64(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
    }

    /**
     * 网关密钥写入 extra 前加密，表单传回掩码或未传密钥时保留原密文。
     *
     * @return array<string,mixed>
     */
    private function filterExtra(mixed $extra, array $exists): array
    {
        $data = $this->decodeExtra($extra);
        $stored = isset($exists['id']) ? $this->storedExtra((int)$exists['id']) : [];
        $secret = $data['gateway_client_secret'] ?? null;
        if (Secret::isMask($secret) || $secret === null) {
            if (array_key_exists('gateway_client_secret', $stored)) {
                $data['gateway_client_secret'] = $stored['gateway_client_secret'];
            } else {
                unset($data['gateway_client_secret']);
            }
        } else {
            $data['gateway_client_secret'] = Secret::encrypt((string)$secret);
        }

        foreach (['gateway_url', 'gateway_client_key'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = trim((string)$data[$field]);
            }
        }
        if (trim((string)($data['gateway_url'] ?? '')) !== '') {
            $this->assertGatewayUrl((string)$data['gateway_url']);
        }

        return $data;
    }

    /**
     * 解析账号扩展配置，JSON 字符串必须是对象，避免格式错误时静默清空网关凭据。
     *
     * @return array<string,mixed>
     */
    private function decodeExtra(mixed $extra): array
    {
        if (is_string($extra)) {
            $decoded = json_decode($extra, true);
            if (!is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
                throw new ErrorResponseException('账号扩展配置格式错误');
            }

            return $decoded;
        }
        if (!is_array($extra) || ($extra !== [] && array_is_list($extra))) {
            throw new ErrorResponseException('账号扩展配置格式错误');
        }

        return $extra;
    }

    /**
     * 网关地址只允许完整 http(s) URL，且不能携带 query/fragment；调用 token 只能由服务端统一追加。
     */
    private function assertGatewayUrl(string $url): void
    {
        if (!preg_match('#^https?://#i', $url)) {
            throw new ErrorResponseException('开放平台网关地址必须是完整 http(s) URL');
        }
        if (parse_url($url, PHP_URL_QUERY) !== null || parse_url($url, PHP_URL_FRAGMENT) !== null || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null) {
            throw new ErrorResponseException('开放平台网关地址不能包含认证信息、查询参数或片段');
        }
    }

    /**
     * 读取数据库中的原始 extra，避免模型输出时对网关密钥做脱敏后影响内部调用。
     *
     * @return array<string,mixed>
     */
    private function rawExtra(WechatClientAccount $account): array
    {
        $raw = $account->getRawOriginal('extra');

        return is_string($raw) ? (json_decode($raw, true) ?: []) : (is_array($raw) ? $raw : []);
    }

    /**
     * 读取数据库中的原始 extra，用于更新时继承已保存的网关密钥。
     *
     * @return array<string,mixed>
     */
    private function storedExtra(int $id): array
    {
        $model = $this->mapper->read($id, isScope: false);

        return $model instanceof WechatClientAccount ? $this->rawExtra($model) : [];
    }

    /**
     * 创建微信 SDK 客户端实例，并注入项目缓存适配器。
     */
    private function sdkClient(): WechatSdkClient
    {
        return new WechatSdkClient(cache: new HyperfCacheStore());
    }
}
