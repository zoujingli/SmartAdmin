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
use System\Service\DeptService;

#[Auth(name: '系统部门管理')]
#[Controller(prefix: 'system/dept')]
final class DeptController extends CoreController
{
    /**
     * @param DeptService $service 部门业务服务
     */
    public function __construct(
        protected DeptService $service
    ) {}

    /**
     * 获取部门分页列表。
     */
    #[GetMapping(path: 'index')]
    #[Auth(name: '系统部门列表', type: Auth::CHECK, menu: true)]
    public function index(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getPageList($request->all()));
    }

    /**
     * 获取部门回收站列表。
     */
    #[GetMapping(path: 'recycle')]
    #[Auth(name: '系统部门管理', type: Auth::CHECK, menu: false, code: 'system.dept.index')]
    public function recycle(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getRecycleList($request->all()));
    }

    /**
     * 获取部门树。
     */
    #[GetMapping(path: 'tree')]
    #[Auth(name: '系统部门树', type: Auth::CHECK, menu: false, code: 'system.dept.index')]
    public function tree(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getTree($request->all()));
    }

    /**
     * 获取部门详情。
     */
    #[GetMapping(path: 'info/{id}')]
    #[Auth(name: '系统部门详情', type: Auth::CHECK, menu: false, code: 'system.dept.index')]
    public function info(int $id): array
    {
        $this->respondFound($this->service->read($id));
    }

    /**
     * 创建部门。
     */
    #[PostMapping(path: 'create')]
    #[Auth(name: '添加系统部门', type: Auth::CHECK, menu: false)]
    #[Logger(name: '新增系统部门')]
    public function create(RequestInterface $request): array
    {
        $this->success('创建成功', $this->service->create($request->all()));
    }

    /**
     * 更新部门。
     */
    #[PutMapping(path: 'update/{id}')]
    #[Auth(name: '编辑系统部门', type: Auth::CHECK, menu: false)]
    #[Logger(name: '编辑系统部门')]
    public function update(int $id, RequestInterface $request): array
    {
        $this->service->update($id, $request->all());
        $this->success('更新成功', $this->service->read($id));
    }

    /**
     * 删除部门（软删）。
     */
    #[DeleteMapping(path: 'delete/{ids}')]
    #[Auth(name: '删除系统部门', type: Auth::CHECK, menu: false)]
    #[Logger(name: '删除系统部门')]
    public function delete(string $ids): array
    {
        $this->deleteByIds($ids, fn (array $idArray) => $this->service->delete($idArray));
    }

    /**
     * 彻底删除部门。
     */
    #[DeleteMapping(path: 'real-delete/{ids}')]
    #[Auth(name: '彻底删除系统部门', type: Auth::CHECK, menu: false)]
    #[Logger(name: '彻底删除系统部门')]
    public function realDelete(string $ids): array
    {
        $this->deleteByIds($ids, fn (array $idArray) => $this->service->delreal($idArray), '彻底删除成功');
    }

    /**
     * 恢复部门。
     */
    #[PutMapping(path: 'recovery/{ids}')]
    #[Auth(name: '恢复系统部门', type: Auth::CHECK, menu: false)]
    #[Logger(name: '恢复系统部门')]
    public function recovery(string $ids): array
    {
        $idArray = $this->idsOrFail($ids);
        $this->service->recovery($idArray);
        $this->success('恢复成功', $idArray);
    }

    /**
     * 更新部门状态。
     */
    #[PutMapping(path: 'status/{id}')]
    #[Auth(name: '更新部门状态', type: Auth::CHECK, menu: false, code: 'system.dept.update')]
    #[Logger(name: '更新部门状态')]
    public function changeStatus(int $id, RequestInterface $request): array
    {
        $status = $request->input('status', 1);
        $this->success('更新成功', $this->service->changeStatus($id, $status));
    }

    /**
     * 更新部门排序。
     */
    #[PutMapping(path: 'sort/{id}')]
    #[Auth(name: '更新部门排序', type: Auth::CHECK, menu: false, code: 'system.dept.update')]
    #[Logger(name: '更新部门排序')]
    public function changeSort(int $id, RequestInterface $request): array
    {
        $sort = $request->input('sort', 0);
        $this->success('更新成功', $this->service->changeSort($id, $sort));
    }

    /**
     * 获取部门选项列表。
     */
    #[GetMapping(path: 'options')]
    #[Auth(name: '获取部门选项', type: Auth::CHECK, menu: false, code: 'system.dept.index')]
    public function options(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getOptions($request->all()));
    }

    /**
     * 获取部门关联用户。
     */
    #[GetMapping(path: 'users/{id}')]
    #[Auth(name: '获取部门用户', type: Auth::CHECK, menu: false, code: 'system.dept.index')]
    public function getUsers(int $id): array
    {
        $this->success('获取成功', $this->service->getDeptUsers($id));
    }

    /**
     * 获取部门统计信息。
     */
    #[GetMapping(path: 'statistics')]
    #[Auth(name: '部门统计', type: Auth::CHECK, menu: false, code: 'system.dept.index')]
    public function statistics(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getStatistics($request->all()));
    }

}
