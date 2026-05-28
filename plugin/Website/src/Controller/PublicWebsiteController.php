<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Plugin\Website\Controller;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Library\CoreController;
use Library\Events\Annotation\Logger;
use Library\Helper\RequestHelper;
use Plugin\Website\Service\WebsiteOpenApiAuthenticator;
use Plugin\Website\Service\WebsitePublicService;
use Plugin\Website\Support\WebsiteOpenApiContext;
use Plugin\Website\Support\WebsiteOpenApiScope;
use Psr\Http\Message\ServerRequestInterface;

#[Controller(prefix: 'website')]
final class PublicWebsiteController extends CoreController
{
    public function __construct(
        protected WebsitePublicService $service,
        protected WebsiteOpenApiAuthenticator $authenticator,
    ) {}

    #[GetMapping(path: 'bootstrap')]
    public function bootstrap(RequestInterface $request): array
    {
        $context = $this->auth($request, WebsiteOpenApiScope::SITE_READ);
        foreach ([WebsiteOpenApiScope::NAV_READ, WebsiteOpenApiScope::CHANNEL_READ, WebsiteOpenApiScope::BLOCK_READ] as $scope) {
            $this->authenticator->assertScope($context, $scope);
        }
        $this->success('获取成功', $this->service->bootstrap($request->all(), $context));
    }

    #[GetMapping(path: 'site')]
    public function site(RequestInterface $request): array
    {
        $context = $this->auth($request, WebsiteOpenApiScope::SITE_READ);
        $this->success('获取成功', $this->service->site($request->all(), $context));
    }

    #[GetMapping(path: 'nav/tree')]
    public function navTree(RequestInterface $request): array
    {
        $context = $this->auth($request, WebsiteOpenApiScope::NAV_READ);
        $this->success('获取成功', $this->service->navTree($request->all(), $context));
    }

    #[GetMapping(path: 'channel/tree')]
    public function channelTree(RequestInterface $request): array
    {
        $context = $this->auth($request, WebsiteOpenApiScope::CHANNEL_READ);
        $this->success('获取成功', $this->service->channelTree($request->all(), $context));
    }

    #[GetMapping(path: 'page')]
    public function page(RequestInterface $request): array
    {
        $context = $this->auth($request, WebsiteOpenApiScope::PAGE_READ);
        // 页面接口是组合读取：返回站点、栏目/内容命中结果以及页面区块，不能只给 page:read 就绕开细分读取权限。
        foreach ([WebsiteOpenApiScope::SITE_READ, WebsiteOpenApiScope::CHANNEL_READ, WebsiteOpenApiScope::CONTENT_READ, WebsiteOpenApiScope::BLOCK_READ] as $scope) {
            $this->authenticator->assertScope($context, $scope);
        }
        $this->success('获取成功', $this->service->page($request->all(), $context));
    }

    #[GetMapping(path: 'content/index')]
    public function contentIndex(RequestInterface $request): array
    {
        $context = $this->auth($request, WebsiteOpenApiScope::CONTENT_READ);
        $this->success('获取成功', $this->service->contentList($request->all(), $context));
    }

    #[GetMapping(path: 'content/info/{id}')]
    public function contentInfo(int $id, RequestInterface $request): array
    {
        $context = $this->auth($request, WebsiteOpenApiScope::CONTENT_READ);
        $this->success('获取成功', $this->service->contentInfo($id, $request->all(), $context));
    }

    #[GetMapping(path: 'content/slug/{slug}')]
    public function contentSlug(string $slug, RequestInterface $request): array
    {
        $context = $this->auth($request, WebsiteOpenApiScope::CONTENT_READ);
        $this->success('获取成功', $this->service->contentBySlug($slug, $request->all(), $context));
    }

    #[GetMapping(path: 'block/list')]
    public function blockList(RequestInterface $request): array
    {
        $context = $this->auth($request, WebsiteOpenApiScope::BLOCK_READ);
        $this->success('获取成功', $this->service->blockList($request->all(), $context));
    }

    #[PostMapping(path: 'lead/create')]
    #[Logger(name: '提交官网线索', excludeFields: ['mobile', 'email', 'app_key'])]
    public function leadCreate(RequestInterface $request): array
    {
        $serverRequest = $request instanceof ServerRequestInterface ? $request : null;
        $context = $this->auth($request, WebsiteOpenApiScope::LEAD_CREATE);
        $this->success('提交成功', $this->service->createLead(
            $request->all(),
            $context,
            RequestHelper::getClientIp($serverRequest),
            (string)($request->getHeader('user-agent')[0] ?? '')
        ));
    }

    private function auth(RequestInterface $request, string $scope): WebsiteOpenApiContext
    {
        $serverRequest = $request instanceof ServerRequestInterface ? $request : null;

        return $this->authenticator->authenticate($request, $scope, RequestHelper::getClientIp($serverRequest));
    }
}
