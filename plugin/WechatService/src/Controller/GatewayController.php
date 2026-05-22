<?php

declare(strict_types=1);

namespace Plugin\WechatService\Controller;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\DeleteMapping;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Annotation\PutMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Library\CoreController;
use Library\Events\Annotation\Auth;
use Library\Events\Annotation\Logger;
use Plugin\WechatService\Service\WechatServiceGatewayService;

#[Auth(name: '微信开放平台网关')]
#[Controller(prefix: 'wechat-service/gateway')]
final class GatewayController extends CoreController
{
    public function __construct(
        protected WechatServiceGatewayService $service
    ) {}

    #[GetMapping(path: 'index')]
    #[Auth(name: '网关凭据列表', type: Auth::CHECK, menu: true, code: 'wechat.service.gateway.index')]
    public function index(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getPageList($request->all()));
    }

    #[PostMapping(path: 'create')]
    #[Auth(name: '新增网关凭据', type: Auth::CHECK, menu: false, code: 'wechat.service.gateway.save')]
    #[Logger(name: '新增网关凭据', excludeFields: ['client_secret'])]
    public function create(RequestInterface $request): array
    {
        $this->success('创建成功', $this->service->createCredential($request->all()));
    }

    #[PutMapping(path: 'update/{id}')]
    #[Auth(name: '编辑网关凭据', type: Auth::CHECK, menu: false, code: 'wechat.service.gateway.save')]
    #[Logger(name: '编辑网关凭据', excludeFields: ['client_secret'])]
    public function update(int $id, RequestInterface $request): array
    {
        $this->service->update($id, $request->all());
        $this->success('更新成功', $this->service->read($id));
    }

    #[PutMapping(path: 'rotate/{id}')]
    #[Auth(name: '轮换网关密钥', type: Auth::CHECK, menu: false, code: 'wechat.service.gateway.save')]
    #[Logger(name: '轮换网关密钥', excludeFields: ['client_secret'])]
    public function rotate(int $id): array
    {
        $this->success('轮换成功', $this->service->rotateSecret($id));
    }

    #[DeleteMapping(path: 'delete/{ids}')]
    #[Auth(name: '删除网关凭据', type: Auth::CHECK, menu: false, code: 'wechat.service.gateway.delete')]
    #[Logger(name: '删除网关凭据')]
    public function delete(string $ids): array
    {
        $this->deleteByIds($ids, fn (array $idArray) => $this->service->delete($idArray));
    }
}
