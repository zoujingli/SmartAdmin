<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace System\Controller;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\DeleteMapping;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Annotation\PutMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Library\CoreController;
use Library\Events\Annotation\Auth;
use Library\Events\Annotation\Logger;
use System\Service\MenuService;

#[Auth(name: '系统菜单管理')]
#[Controller(prefix: 'system/menu')]
final class MenuController extends CoreController
{
    /**
     * @param MenuService $service 菜单业务服务
     */
    public function __construct(
        protected MenuService $service
    ) {}

    /**
     * 获取菜单分页列表。
     */
    #[GetMapping(path: 'index')]
    #[Auth(name: '系统菜单列表', type: Auth::CHECK, menu: true)]
    public function index(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getPageList($request->all()));
    }

    /**
     * 获取菜单回收站列表。
     */
    #[GetMapping(path: 'recycle')]
    #[Auth(name: '系统菜单管理', type: Auth::CHECK, menu: false, code: 'system.menu.index')]
    public function recycle(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getRecycleList($request->all()));
    }

    /**
     * 获取菜单树结构。
     */
    #[GetMapping(path: 'tree')]
    #[Auth(name: '系统菜单树', type: Auth::CHECK, menu: false, code: 'system.menu.index')]
    public function tree(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getTree($request->all()));
    }

    /**
     * 获取菜单详情。
     */
    #[GetMapping(path: 'info/{id}')]
    #[Auth(name: '系统菜单详情', type: Auth::CHECK, menu: false, code: 'system.menu.index')]
    public function info(int $id): array
    {
        $this->respondFound($this->service->read($id));
    }

    /**
     * 创建菜单。
     */
    #[PostMapping(path: 'create')]
    #[Auth(name: '新增系统菜单', type: Auth::CHECK, menu: false)]
    #[Logger(name: '新增系统菜单')]
    public function create(RequestInterface $request): array
    {
        $this->success('创建成功', $this->service->create($request->all()));
    }

    /**
     * 更新菜单。
     */
    #[PutMapping(path: 'update/{id}')]
    #[Auth(name: '编辑系统菜单', type: Auth::CHECK, menu: false)]
    #[Logger(name: '编辑系统菜单')]
    public function update(int $id, RequestInterface $request): array
    {
        $this->service->update($id, $request->all());
        $this->success('更新成功', $this->service->read($id));
    }

    /**
     * 删除菜单（软删）。
     */
    #[DeleteMapping(path: 'delete/{ids}')]
    #[Auth(name: '删除菜单记录', type: Auth::CHECK, menu: false)]
    #[Logger(name: '删除系统菜单')]
    public function delete(string $ids): array
    {
        $this->deleteByIds($ids, fn (array $idArray) => $this->service->delete($idArray));
    }

    /**
     * 彻底删除菜单。
     */
    #[DeleteMapping(path: 'real-delete/{ids}')]
    #[Auth(name: '彻底删除菜单记录', type: Auth::CHECK, menu: false)]
    #[Logger(name: '彻底删除系统菜单')]
    public function realDelete(string $ids): array
    {
        $this->deleteByIds($ids, fn (array $idArray) => $this->service->delreal($idArray), '彻底删除成功');
    }

    /**
     * 恢复菜单。
     */
    #[PutMapping(path: 'recovery/{ids}')]
    #[Auth(name: '恢复系统菜单', type: Auth::CHECK, menu: false)]
    #[Logger(name: '恢复系统菜单')]
    public function recovery(string $ids): array
    {
        $idArray = $this->idsOrFail($ids);
        $this->service->recovery($idArray);
        $this->success('恢复成功', $idArray);
    }

    /**
     * 更新菜单状态。
     */
    #[PutMapping(path: 'status/{id}')]
    #[Auth(name: '更新菜单状态', type: Auth::CHECK, menu: false, code: 'system.menu.update')]
    #[Logger(name: '更新菜单状态')]
    public function changeStatus(int $id, RequestInterface $request): array
    {
        $status = $request->input('status', 1);
        $this->success('更新成功', $this->service->changeStatus($id, $status));
    }

    /**
     * 更新菜单排序。
     */
    #[PutMapping(path: 'sort/{id}')]
    #[Auth(name: '更新菜单排序', type: Auth::CHECK, menu: false, code: 'system.menu.update')]
    #[Logger(name: '更新菜单排序')]
    public function changeSort(int $id, RequestInterface $request): array
    {
        $sort = $request->input('sort', 0);
        $this->success('更新成功', $this->service->changeSort($id, $sort));
    }

    /**
     * 获取菜单选项列表。
     */
    #[GetMapping(path: 'options')]
    #[Auth(name: '获取菜单选项', type: Auth::CHECK, menu: false, code: 'system.menu.index')]
    public function options(): array
    {
        $this->success('获取成功', $this->service->getOptions());
    }

    /**
     * 获取后台注解权限节点建议。
     */
    #[GetMapping(path: 'node-options')]
    #[Auth(name: '系统菜单列表', type: Auth::CHECK, menu: false, code: 'system.menu.index')]
    public function nodeOptions(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getNodeOptions($request->all()));
    }

    /**
     * 获取当前用户菜单。
     */
    #[GetMapping(path: 'user')]
    #[Auth(name: '获取用户菜单', type: Auth::LOGIN, menu: false, code: 'system.menu.index')]
    public function userMenus(): array
    {
        $this->success('获取成功', $this->service->getUserMenus());
    }

    /**
     * 获取当前用户菜单权限码。
     */
    #[GetMapping(path: 'permissions')]
    #[Auth(name: '获取菜单权限', type: Auth::LOGIN, menu: false, code: 'system.menu.index')]
    public function permissions(): array
    {
        $this->success('获取成功', $this->service->getPermissions());
    }

    /**
     * 获取菜单统计信息。
     */
    #[GetMapping(path: 'statistics')]
    #[Auth(name: '菜单统计', type: Auth::CHECK, menu: false, code: 'system.menu.index')]
    public function statistics(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getStatistics($request->all()));
    }
}
