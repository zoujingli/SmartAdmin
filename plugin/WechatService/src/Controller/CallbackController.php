<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Plugin\WechatService\Controller;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Library\CoreController;
use Plugin\WechatService\Service\WechatServiceAuthService;
use Plugin\WechatService\Service\WechatServiceConfigService;
use Plugin\WechatService\Support\BindState;
use Psr\Http\Message\ResponseInterface;

#[Controller(prefix: 'wechat-service/api/callback')]
final class CallbackController extends CoreController
{
    public function __construct(
        protected WechatServiceConfigService $service,
        private readonly WechatServiceAuthService $authorizers,
    ) {}

    #[GetMapping(path: 'auth')]
    public function auth(RequestInterface $request): ResponseInterface
    {
        $tenantId = BindState::verify((string)$request->input('state', ''));
        $this->authorizers->handleAuthCallback((string)$request->input('auth_code', ''), $tenantId);

        return $this->response->raw('success');
    }

    #[PostMapping(path: 'ticket')]
    public function ticket(RequestInterface $request): ResponseInterface
    {
        $this->service->handleTicketCallback((string)$request->getBody(), $request->all());

        return $this->response->raw('success');
    }

    #[GetMapping(path: 'ticket')]
    public function verifyTicket(RequestInterface $request): ResponseInterface
    {
        // 微信开放平台保存回调地址时会发起 URL 接入验证；必须校验签名后原样返回 echostr。
        return $this->response->raw($this->service->verifyPlainCallback($request->all()));
    }

    #[PostMapping(path: 'notify/{appid}')]
    public function notifyAuthorizer(string $appid, RequestInterface $request): ResponseInterface
    {
        return $this->handleNotify($request, $appid);
    }

    #[GetMapping(path: 'notify/{appid}')]
    public function verifyNotifyAuthorizer(string $appid, RequestInterface $request): ResponseInterface
    {
        // 开放平台消息与事件接收 URL 常配置为带 $APPID$ 的路径，GET 接入验证时 AppID 只用于路由匹配。
        return $this->response->raw($this->service->verifyPlainCallback($request->all()));
    }

    private function handleNotify(RequestInterface $request, string $appid): ResponseInterface
    {
        // 授权账号消息与事件回调完成验签、解密、审计和租户侧分发；被动回复需要按原安全模式输出。
        $result = $this->service->handleNotifyCallback((string)$request->getBody(), $request->all(), $appid);
        $replyXml = (string)($result['_reply_xml'] ?? '');
        if ($replyXml !== '') {
            if ($request->has('msg_signature')) {
                try {
                    $replyXml = $this->service->encryptCallbackReply($replyXml, $request->all());
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
