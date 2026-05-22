<?php

declare(strict_types=1);

namespace Plugin\WechatService\Controller;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Library\CoreController;
use Library\Events\Annotation\Auth;
use Library\Events\Annotation\Logger;
use Plugin\WechatService\Service\WechatServiceConfigService;
use Plugin\WechatService\Support\BindState;

#[Auth(name: '微信开放平台配置')]
#[Controller(prefix: 'wechat-service/config')]
final class ConfigController extends CoreController
{
    public function __construct(
        protected WechatServiceConfigService $service
    ) {}

    #[GetMapping(path: 'index')]
    #[Auth(name: '开放平台配置', type: Auth::CHECK, menu: true, code: 'wechat.service.config.index')]
    public function index(): array
    {
        $this->success('获取成功', $this->service->detail());
    }

    #[PostMapping(path: 'save')]
    #[Auth(name: '保存开放平台配置', type: Auth::CHECK, menu: false, code: 'wechat.service.config.save')]
    #[Logger(name: '保存开放平台配置', excludeFields: ['component_appsecret', 'component_token', 'component_encodingaeskey', 'component_verify_ticket', 'component_access_token'])]
    public function save(RequestInterface $request): array
    {
        $this->success('保存成功', $this->service->save($request->all()));
    }

    #[PostMapping(path: 'auth-url')]
    #[Auth(name: '生成开放平台授权链接', type: Auth::CHECK, menu: false, code: 'wechat.service.config.auth-url')]
    public function authUrl(RequestInterface $request): array
    {
        $url = $this->service->authorizationUrl(
            (string)$request->input('redirect_uri', ''),
            (int)$request->input('auth_type', 3),
            (string)$request->input('state', '') ?: BindState::make((int)$request->input('tenant_id', tenant_id()))
        );

        $this->success('生成成功', ['url' => $url]);
    }
}
