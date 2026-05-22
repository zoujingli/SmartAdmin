<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://github.com/zoujingli/SmartAdmin/blob/master/readme.md
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
use System\Service\RoleService;

#[Auth(name: '系统角色管理')]
#[Controller(prefix: 'system/role')]
final class RoleController extends CoreController
{
    /**
     * @param RoleService $service 角色业务服务
     */
    public function __construct(
        protected RoleService $service
    ) {}

    /**
     * 获取角色分页列表。
     */
    #[GetMapping(path: 'index')]
    #[Auth(name: '系统角色列表', type: Auth::CHECK, menu: true)]
    public function index(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getPageList($request->all()));
    }

    /**
     * 获取角色回收站列表。
     */
    #[GetMapping(path: 'recycle')]
    #[Auth(name: '系统角色管理', type: Auth::CHECK, menu: false, code: 'system.role.index')]
    public function recycle(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getRecycleList($request->all()));
    }

    /**
     * 获取角色详情。
     */
    #[GetMapping(path: 'info/{id}')]
    #[Auth(name: '系统角色详情', type: Auth::CHECK, menu: false, code: 'system.role.index')]
    public function info(int $id): array
    {
        $this->respondFound($this->service->read($id));
    }

    /**
     * 创建角色。
     */
    #[PostMapping(path: 'create')]
    #[Auth(name: '新增系统角色', type: Auth::CHECK, menu: false)]
    #[Logger(name: '新增系统角色')]
    public function create(RequestInterface $request): array
    {
        $this->success('创建成功', $this->service->create($request->all()));
    }

    /**
     * 更新角色。
     */
    #[PutMapping(path: 'update/{id}')]
    #[Auth(name: '编辑系统角色', type: Auth::CHECK, menu: false)]
    #[Logger(name: '编辑系统角色')]
    public function update(int $id, RequestInterface $request): array
    {
        $this->service->update($id, $request->all());
        $this->success('更新成功', $this->service->read($id));
    }

    /**
     * 删除角色（软删）。
     */
    #[DeleteMapping(path: 'delete/{ids}')]
    #[Auth(name: '删除系统角色', type: Auth::CHECK, menu: false)]
    #[Logger(name: '删除系统角色')]
    public function delete(string $ids): array
    {
        $this->deleteByIds($ids, fn (array $idArray) => $this->service->delete($idArray));
    }

    /**
     * 彻底删除角色。
     */
    #[DeleteMapping(path: 'real-delete/{ids}')]
    #[Auth(name: '彻底删除系统角色', type: Auth::CHECK, menu: false)]
    #[Logger(name: '彻底删除系统角色')]
    public function realDelete(string $ids): array
    {
        $this->deleteByIds($ids, fn (array $idArray) => $this->service->delreal($idArray), '彻底删除成功');
    }

    /**
     * 恢复角色。
     */
    #[PutMapping(path: 'recovery/{ids}')]
    #[Auth(name: '恢复系统角色', type: Auth::CHECK, menu: false)]
    #[Logger(name: '恢复系统角色')]
    public function recovery(string $ids): array
    {
        $idArray = $this->idsOrFail($ids);
        $this->service->recovery($idArray);
        $this->success('恢复成功', $idArray);
    }

    /**
     * 更新角色状态。
     */
    #[PutMapping(path: 'status/{id}')]
    #[Auth(name: '更新角色状态', type: Auth::CHECK, menu: false, code: 'system.role.update')]
    #[Logger(name: '更新角色状态')]
    public function changeStatus(int $id, RequestInterface $request): array
    {
        $status = $request->input('status', 1);
        $this->success('状态更新成功', $this->service->changeStatus($id, $status));
    }

    /**
     * 分配角色权限节点。
     */
    #[PutMapping(path: 'nodes/{id}')]
    #[Auth(name: '分配角色权限', type: Auth::CHECK, menu: false, code: 'system.role.assign')]
    #[Logger(name: '分配角色权限')]
    public function assignNodes(int $id, RequestInterface $request): array
    {
        $nodes = $request->all()['nodes'] ?? [];
        $this->success('权限分配成功', $this->service->assignNodes($id, $nodes));
    }

    /**
     * 获取角色权限节点。
     */
    #[GetMapping(path: 'nodes/{id}')]
    #[Auth(name: '获取角色权限', type: Auth::CHECK, menu: false, code: 'system.role.assign')]
    public function getNodes(int $id): array
    {
        $this->success('获取成功', $this->service->getRoleNodes($id));
    }

    /**
     * 获取角色关联用户。
     */
    #[GetMapping(path: 'users/{id}')]
    #[Auth(name: '获取角色用户', type: Auth::CHECK, menu: false, code: 'system.role.index')]
    public function getUsers(int $id): array
    {
        $this->success('获取成功', $this->service->getRoleUsers($id));
    }

    /**
     * 获取角色选项列表。
     */
    #[GetMapping(path: 'options')]
    #[Auth(name: '获取角色选项', type: Auth::CHECK, menu: false, code: 'system.role.index')]
    public function options(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getOptions($request->all()));
    }

    /**
     * 获取可授权菜单树。
     */
    #[GetMapping(path: 'permission-tree')]
    #[Auth(name: '获取角色授权菜单', type: Auth::CHECK, menu: false, code: 'system.role.assign')]
    public function permissionTree(): array
    {
        $this->success('获取成功', $this->service->getPermissionTree());
    }

    /**
     * 获取角色统计信息。
     */
    #[GetMapping(path: 'statistics')]
    #[Auth(name: '角色统计', type: Auth::CHECK, menu: false, code: 'system.role.index')]
    public function statistics(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getStatistics($request->all()));
    }

}
