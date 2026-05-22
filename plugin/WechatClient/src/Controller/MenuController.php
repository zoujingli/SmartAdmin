<?php

declare(strict_types=1);

namespace Plugin\WechatClient\Controller;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\DeleteMapping;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Annotation\PutMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Library\CoreController;
use Library\Events\Annotation\Auth;
use Library\Events\Annotation\Logger;
use Plugin\WechatClient\Service\WechatClientMenuService;

/**
 * 微信菜单后台管理接口。
 *
 * 负责本地菜单方案的维护，并将菜单发布到微信官方接口。
 */
#[Auth(name: '微信菜单')]
#[Controller(prefix: 'wechat-client/menu')]
final class MenuController extends CoreController
{
    public function __construct(
        protected WechatClientMenuService $service
    ) {}

    /**
     * 获取本地微信菜单方案列表。
     */
    #[GetMapping(path: 'index')]
    #[Auth(name: '微信菜单列表', type: Auth::CHECK, menu: true, code: 'wechat.client.menu.index')]
    public function index(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getPageList($request->all()));
    }

    /**
     * 创建微信菜单方案。
     */
    #[PostMapping(path: 'create')]
    #[Auth(name: '保存微信菜单', type: Auth::CHECK, menu: false, code: 'wechat.client.menu.save')]
    #[Logger(name: '保存微信菜单')]
    public function create(RequestInterface $request): array
    {
        $this->success('创建成功', $this->service->create($request->all()));
    }

    /**
     * 更新微信菜单方案。
     */
    #[PutMapping(path: 'update/{id}')]
    #[Auth(name: '编辑微信菜单', type: Auth::CHECK, menu: false, code: 'wechat.client.menu.save')]
    #[Logger(name: '编辑微信菜单')]
    public function update(int $id, RequestInterface $request): array
    {
        $this->service->update($id, $request->all());
        $this->success('更新成功', $this->service->read($id));
    }

    /**
     * 将本地菜单方案发布到微信。
     */
    #[PostMapping(path: 'publish/{id}')]
    #[Auth(name: '发布微信菜单', type: Auth::CHECK, menu: false, code: 'wechat.client.menu.publish')]
    #[Logger(name: '发布微信菜单')]
    public function publish(int $id): array
    {
        $this->success('发布成功', $this->service->publish($id));
    }

    /**
     * 批量删除本地菜单方案。
     */
    #[DeleteMapping(path: 'delete/{ids}')]
    #[Auth(name: '删除微信菜单', type: Auth::CHECK, menu: false, code: 'wechat.client.menu.delete')]
    #[Logger(name: '删除微信菜单')]
    public function delete(string $ids): array
    {
        $this->deleteByIds($ids, fn (array $idArray) => $this->service->delete($idArray));
    }
}
