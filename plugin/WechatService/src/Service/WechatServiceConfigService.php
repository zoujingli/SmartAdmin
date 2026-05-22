<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://doc.hyperf.thinkadmin.top
 */

namespace Plugin\WechatService\Service;

use Library\Constants\Status;
use Library\CoreService;
use Library\Exception\ErrorResponseException;
use Plugin\WechatService\Mapper\WechatServiceConfigMapper;
use Plugin\WechatService\Model\WechatServiceConfig;
use Plugin\WechatService\Support\HyperfCacheStore;
use Plugin\WechatService\Support\Secret;
use We\Client as WechatSdkClient;
use We\Config\WechatServiceConfig as SdkWechatServiceConfig;
use We\Platform\Wechat\ServiceClient as WechatServiceSdkClient;
use We\Support\Signature;
use We\Support\Xml;

final class WechatServiceConfigService extends CoreService
{
    private const SECRET_FIELDS = [
        'component_appsecret',
        'component_token',
        'component_encodingaeskey',
        'component_verify_ticket',
        'component_access_token',
    ];

    public function __construct(
        protected WechatServiceConfigMapper $mapper,
        private readonly WechatServiceLoggerService $logs,
        private readonly WechatServiceNotifyDispatcher $notifies,
    ) {}

    /**
     * 获取当前平台配置，敏感字段只返回 configured/masked 状态。
     *
     * @return array<string,mixed>
     */
    public function detail(): array
    {
        $config = $this->mapper->active();
        $data = $config?->toArray() ?? [];
        foreach (self::SECRET_FIELDS as $field) {
            $data[$field . '_configured'] = $config ? Secret::decrypt((string)($config->{$field} ?? '')) !== '' : false;
            $data[$field] = $data[$field . '_configured'] ? Secret::mask('configured') : '';
        }
        $data['callback_urls'] = [
            'ticket' => '/wechat-service/api/callback/ticket',
            'message' => '/wechat-service/api/callback/notify/$APPID$',
            'jsonrpc' => '/wechat-service/api/rpc/jsonrpc?token=TOKEN',
        ];

        return $data;
    }

    /**
     * 保存平台配置；掩码字段表示沿用旧密文，空字符串表示清空。
     */
    public function save(array $data): WechatServiceConfig
    {
        $exists = $this->mapper->active();
        $secretInput = [];
        foreach (self::SECRET_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $secretInput[$field] = $data[$field];
            }
        }

        $payload = $this->filterData($data, $exists?->toArray() ?? []);
        foreach (self::SECRET_FIELDS as $field) {
            if (!array_key_exists($field, $secretInput)) {
                unset($payload[$field]);
                continue;
            }
            if (Secret::isMask($secretInput[$field])) {
                unset($payload[$field]);
                continue;
            }
            // _vali() 会过滤不在规则中的字段，敏感配置必须先从原始输入快照读取，再单独加密写入。
            $payload[$field] = Secret::encrypt((string)$secretInput[$field]);
        }

        if ($exists instanceof WechatServiceConfig) {
            $this->mapper->update($exists, $payload);

            $updated = $this->mapper->read($exists->id, isScope: false);
            if (!$updated instanceof WechatServiceConfig) {
                throw new ErrorResponseException('开放平台配置保存失败');
            }

            return $updated;
        }

        /** @var WechatServiceConfig $created */
        return $this->mapper->create($payload);
    }

    /**
     * 生成开放平台授权链接；需要先通过 ticket 回调拿到 component_verify_ticket。
     */
    public function authorizationUrl(string $redirectUri, int $authType = 3, string $state = ''): string
    {
        if (!preg_match('#^https?://#i', trim($redirectUri))) {
            throw new ErrorResponseException('授权回调地址必须是完整 URL');
        }
        if (!in_array($authType, [1, 2, 3], true)) {
            throw new ErrorResponseException('授权类型错误');
        }

        $config = $this->requireConfig();
        $ticket = Secret::decrypt((string)$config->component_verify_ticket);
        if ($ticket === '') {
            throw new ErrorResponseException('尚未收到开放平台 component_verify_ticket');
        }

        $client = $this->serviceClient();
        $componentAccessToken = $client->componentAccessToken($ticket);
        $preAuth = $client->createPreAuthCode($componentAccessToken);

        return $client->authorizationUrl((string)($preAuth['pre_auth_code'] ?? ''), $redirectUri, $authType, $state);
    }

    public function openClient(): WechatSdkClient
    {
        return new WechatSdkClient(cache: new HyperfCacheStore());
    }

    public function componentVerifyTicket(): string
    {
        return Secret::decrypt((string)$this->requireConfig()->component_verify_ticket);
    }

    public function componentAccessToken(): string
    {
        $ticket = $this->componentVerifyTicket();
        if ($ticket === '') {
            throw new ErrorResponseException('尚未收到开放平台 component_verify_ticket');
        }

        return $this->serviceClient()->componentAccessToken($ticket);
    }

    public function sdkConfig(): SdkWechatServiceConfig
    {
        $config = $this->requireConfig();

        return new SdkWechatServiceConfig(
            (string)$config->component_appid,
            Secret::decrypt((string)$config->component_appsecret),
            Secret::decrypt((string)$config->component_token),
            Secret::decrypt((string)$config->component_encodingaeskey),
            'cfg:' . (string)$config->id,
        );
    }

    /**
     * 处理开放平台 Ticket 推送；加密模式通过 SDK 解密，明文模式仅用于本地联调。
     *
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public function handleTicketCallback(string $body, array $query): array
    {
        $payload = $this->decodeCallbackPayload($body, $query);
        $ticket = (string)($payload['ComponentVerifyTicket'] ?? '');
        $infoType = strtolower(trim((string)($payload['InfoType'] ?? 'component_event')));
        if ($ticket === '' && $infoType === 'component_verify_ticket') {
            throw new ErrorResponseException('Ticket 回调数据无效');
        }

        $config = $this->requireConfig();
        if ($ticket !== '') {
            // component_verify_ticket 是开放平台令牌续期的前置凭证，只在微信推送了有效票据时覆盖本地密文。
            $this->mapper->update($config, ['component_verify_ticket' => Secret::encrypt($ticket)]);
        }
        $appid = (string)($payload['AuthorizerAppid'] ?? $payload['ComponentAppid'] ?? $config->component_appid);
        $this->logs->record($infoType !== '' ? $infoType : 'component_event', $appid, $payload);
        // 授权、更新授权、取消授权等事件与 Ticket 推送共用“授权事件接收 URL”，不能因缺少 Ticket 丢弃。
        // 当前分发器会对 unauthorized 做本地禁用处理，其它授权资料仍以授权回调和后台同步为准。
        $this->notifies->dispatch($payload);

        return $payload;
    }

    /**
     * 处理开放平台普通事件回调。
     *
     * 回调入口先完成验签/解密和审计落库，再按授权账号 AppID 分发给 WechatClient 的粉丝与自动回复链路；
     * 若产生被动回复 XML，会暂存到返回数组的 _reply_xml 字段，由控制器按原回调加密模式输出。
     *
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public function handleNotifyCallback(string $body, array $query, string $routeAppid): array
    {
        $payload = $this->decodeCallbackPayload($body, $query);
        $routeAppid = trim($routeAppid);
        if ($routeAppid === '') {
            throw new ErrorResponseException('授权账号 AppID 不能为空');
        }
        // 开放平台普通消息 XML 通常只有 ToUserName 原始 ID（gh_xxx），授权方 AppID 必须从标准 URL 的 $APPID$ 占位取得。
        // 路由 AppID 是当前消息归属的唯一可信来源，避免 payload 中其它 AppId 字段造成误判。
        $payload['AuthorizerAppid'] = $routeAppid;

        $event = (string)($payload['InfoType'] ?? $payload['Event'] ?? $payload['MsgType'] ?? 'notify');
        $appid = (string)($payload['AuthorizerAppid'] ?? $payload['AppId'] ?? $payload['appid'] ?? $payload['ToUserName'] ?? $this->requireConfig()->component_appid);
        $this->logs->record($event, $appid, $payload);
        if (($replyXml = $this->notifies->dispatch($payload)) !== null && $replyXml !== '') {
            $payload['_reply_xml'] = $replyXml;
        }

        return $payload;
    }

    /**
     * 校验微信回调 URL 接入验证请求，并返回需要原样输出的 echostr。
     *
     * @param array<string,mixed> $query
     */
    public function verifyPlainCallback(array $query): string
    {
        $token = Secret::decrypt((string)$this->requireConfig()->component_token);
        if ($token === '') {
            throw new ErrorResponseException('开放平台回调 Token 未配置');
        }
        Signature::assertSha1((string)($query['signature'] ?? ''), [
            $token,
            (string)($query['timestamp'] ?? ''),
            (string)($query['nonce'] ?? ''),
        ]);

        return (string)($query['echostr'] ?? 'success');
    }

    /**
     * 开放平台安全模式下，被动回复必须使用第三方平台消息密钥重新加密。
     *
     * @param array<string,mixed> $query
     */
    public function encryptCallbackReply(string $xml, array $query): string
    {
        $result = $this->serviceClient()->post('encrypt_message', [
            'body' => $xml,
            'timestamp' => (string)($query['timestamp'] ?? time()),
            'nonce' => (string)($query['nonce'] ?? ''),
        ]);

        return (string)($result['xml'] ?? '');
    }

    protected function filterData(array &$data, array $exists = []): array
    {
        $rules = [
            'name.max:100' => '配置名称最多 100 位',
            'component_appid.filled' => '第三方平台 AppID 不能为空',
            'component_appid.max:64' => '第三方平台 AppID 最多 64 位',
            'status.integer' => '状态值必须为数字',
            'status.in:1,0' => '状态值错误',
            'remark.max:255' => '备注最多 255 位',
        ];
        if ($exists === []) {
            $rules['name.default'] = '默认开放平台';
            $rules['component_appid.required'] = '第三方平台 AppID 不能为空';
            $rules['status.default'] = Status::ENABLED;
        }

        $data = _vali($rules, $data);
        if (array_key_exists('component_appid', $data) && $this->mapper->existsByComponentAppid((string)$data['component_appid'], (int)($exists['id'] ?? 0))) {
            throw new ErrorResponseException('第三方平台 AppID 已存在');
        }
        foreach (['status'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = (int)$data[$field];
            }
        }

        return $data;
    }

    private function requireConfig(): WechatServiceConfig
    {
        $config = $this->mapper->active();
        if (!$config instanceof WechatServiceConfig || !Status::isEnabled((int)$config->status)) {
            throw new ErrorResponseException('微信开放平台配置未启用');
        }

        return $config;
    }

    private function serviceClient(): WechatServiceSdkClient
    {
        return $this->openClient()->wechatService($this->sdkConfig());
    }

    /**
     * 解析开放平台公网回调。
     *
     * 加密模式由 SDK 使用 msg_signature 验签并解密；明文模式仍必须校验 signature/timestamp/nonce，
     * 避免攻击者伪造 Ticket 或普通事件写入平台配置和审计日志。
     *
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    private function decodeCallbackPayload(string $body, array $query): array
    {
        if (isset($query['msg_signature'])) {
            return $this->serviceClient()->post('decrypt_message', [
                'body' => $body,
                'msg_signature' => (string)$query['msg_signature'],
                'timestamp' => (string)($query['timestamp'] ?? ''),
                'nonce' => (string)($query['nonce'] ?? ''),
            ]);
        }

        $token = Secret::decrypt((string)$this->requireConfig()->component_token);
        if ($token === '') {
            throw new ErrorResponseException('开放平台回调 Token 未配置');
        }
        Signature::assertSha1((string)($query['signature'] ?? ''), [
            $token,
            (string)($query['timestamp'] ?? ''),
            (string)($query['nonce'] ?? ''),
        ]);

        return Xml::decode($body);
    }
}
