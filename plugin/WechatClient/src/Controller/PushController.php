<?php

declare(strict_types=1);

namespace Plugin\WechatClient\Controller;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Library\Constants\System;
use Library\CoreController;
use Plugin\WechatClient\Service\WechatClientAccountService;
use Plugin\WechatClient\Service\WechatClientUserService;
use Plugin\WechatClient\Service\WechatClientReplyRuleService;
use Psr\Http\Message\ResponseInterface;
use We\Support\Xml;

/**
 * 公众号官方推送入口。
 *
 * 负责接收微信服务器推送，完成消息解密/解析，并把关注类事件转交给粉丝服务派发内部事件。
 */
#[Controller(prefix: 'wechat-client/api')]
final class PushController extends CoreController
{
    public function __construct(
        protected WechatClientAccountService $service,
        private readonly WechatClientUserService $users,
        private readonly WechatClientReplyRuleService $replies,
    ) {}

    /**
     * 微信公众号服务器配置 URL 接入验证。
     */
    #[GetMapping(path: 'push/{appid}')]
    public function verify(string $appid, RequestInterface $request): ResponseInterface
    {
        $account = $this->service->findByAppidForCallback($appid);
        // URL 验证阶段仅校验 token 签名并原样返回 echostr，不进入粉丝和自动回复业务链路。
        $this->service->assertPlainCallbackSignature($account, $request->all());

        return $this->response->raw((string)$request->input('echostr', ''));
    }

    /**
     * 接收指定 AppID 的公众号推送消息。
     */
    #[PostMapping(path: 'push/{appid}')]
    public function push(string $appid, RequestInterface $request): ResponseInterface
    {
        $account = $this->service->findByAppidForCallback($appid);
        System::setTenantId((int)$account->tenant_id);
        $body = (string)$request->getBody();
        $encrypted = $request->has('msg_signature');
        if (!$encrypted) {
            $this->service->assertPlainCallbackSignature($account, $request->all());
        }
        $payload = $encrypted
            ? $this->service->officialRequest($account, 'decrypt_message', [
                'body' => $body,
                'msg_signature' => (string)$request->input('msg_signature'),
                'timestamp' => (string)$request->input('timestamp'),
                'nonce' => (string)$request->input('nonce'),
            ])
            : Xml::decode($body);

        // 订阅/取消订阅由微信官方推送触发，统一转换为内部事件，业务模块按需监听。
        $this->users->handleOfficialPush($account, $payload);
        $replyXml = $this->replies->handleOfficialPush($account, $payload);
        if (is_string($replyXml) && $replyXml !== '') {
            if ($encrypted) {
                // 安全模式下被动回复必须重新加密；加密失败时降级 success，避免微信重复推送造成风暴。
                try {
                    $result = $this->service->officialRequest($account, 'encrypt_message', [
                        'body' => $replyXml,
                        'timestamp' => (string)$request->input('timestamp', (string)time()),
                        'nonce' => (string)$request->input('nonce', ''),
                    ]);
                    $replyXml = (string)($result['xml'] ?? '');
                } catch (\Throwable) {
                    $replyXml = '';
                }
            }

            if ($replyXml !== '') {
                return $this->response->raw($replyXml);
            }
        }

        return $this->response->raw('success');
    }
}
