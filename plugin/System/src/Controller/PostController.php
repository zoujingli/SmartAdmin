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
use System\Service\PostService;

#[Auth(name: '系统岗位管理')]
#[Controller(prefix: 'system/post')]
final class PostController extends CoreController
{
    /**
     * @param PostService $service 岗位业务服务
     */
    public function __construct(
        protected PostService $service
    ) {}

    /**
     * 获取岗位分页列表。
     */
    #[GetMapping(path: 'index')]
    #[Auth(name: '系统岗位列表', type: Auth::CHECK, menu: true)]
    public function index(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getPageList($request->all()));
    }

    /**
     * 获取岗位回收站列表。
     */
    #[GetMapping(path: 'recycle')]
    #[Auth(name: '系统岗位管理', type: Auth::CHECK, menu: false, code: 'system.post.index')]
    public function recycle(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getRecycleList($request->all()));
    }

    /**
     * 获取岗位详情。
     */
    #[GetMapping(path: 'info/{id}')]
    #[Auth(name: '系统岗位详情', type: Auth::CHECK, menu: false, code: 'system.post.index')]
    public function info(int $id): array
    {
        $this->respondFound($this->service->read($id));
    }

    /**
     * 创建岗位。
     */
    #[PostMapping(path: 'create')]
    #[Auth(name: '添加系统岗位', type: Auth::CHECK, menu: false)]
    #[Logger(name: '新增系统岗位')]
    public function create(RequestInterface $request): array
    {
        $this->success('创建成功', $this->service->create($request->all()));
    }

    /**
     * 更新岗位。
     */
    #[PutMapping(path: 'update/{id}')]
    #[Auth(name: '编辑系统岗位', type: Auth::CHECK, menu: false)]
    #[Logger(name: '编辑系统岗位')]
    public function update(int $id, RequestInterface $request): array
    {
        $this->service->update($id, $request->all());
        $this->success('更新成功', $this->service->read($id));
    }

    /**
     * 删除岗位（软删）。
     */
    #[DeleteMapping(path: 'delete/{ids}')]
    #[Auth(name: '删除系统岗位', type: Auth::CHECK, menu: false)]
    #[Logger(name: '删除系统岗位')]
    public function delete(string $ids): array
    {
        $this->deleteByIds($ids, fn (array $idArray) => $this->service->delete($idArray));
    }

    /**
     * 彻底删除岗位。
     */
    #[DeleteMapping(path: 'real-delete/{ids}')]
    #[Auth(name: '彻底删除系统岗位', type: Auth::CHECK, menu: false)]
    #[Logger(name: '彻底删除系统岗位')]
    public function realDelete(string $ids): array
    {
        $this->deleteByIds($ids, fn (array $idArray) => $this->service->delreal($idArray), '彻底删除成功');
    }

    /**
     * 恢复岗位。
     */
    #[PutMapping(path: 'recovery/{ids}')]
    #[Auth(name: '恢复系统岗位', type: Auth::CHECK, menu: false)]
    #[Logger(name: '恢复系统岗位')]
    public function recovery(string $ids): array
    {
        $idArray = $this->idsOrFail($ids);
        $this->service->recovery($idArray);
        $this->success('恢复成功', $idArray);
    }

    /**
     * 更新岗位状态。
     */
    #[PutMapping(path: 'status/{id}')]
    #[Auth(name: '更新岗位状态', type: Auth::CHECK, menu: false, code: 'system.post.update')]
    #[Logger(name: '更新岗位状态')]
    public function changeStatus(int $id, RequestInterface $request): array
    {
        $status = $request->input('status', 1);
        $this->success('状态更新成功', $this->service->changeStatus($id, $status));
    }

    /**
     * 获取岗位选项列表。
     */
    #[GetMapping(path: 'options')]
    #[Auth(name: '获取岗位选项', type: Auth::CHECK, menu: false, code: 'system.post.index')]
    public function options(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getOptions($request->all()));
    }

    /**
     * 获取岗位统计信息。
     */
    #[GetMapping(path: 'statistics')]
    #[Auth(name: '岗位统计', type: Auth::CHECK, menu: false, code: 'system.post.index')]
    public function statistics(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getStatistics($request->all()));
    }

}
