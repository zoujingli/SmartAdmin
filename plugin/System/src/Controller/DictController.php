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
use System\Service\DictService;

#[Auth(name: '系统字典管理')]
#[Controller(prefix: 'system/dict')]
final class DictController extends CoreController
{
    /**
     * @param DictService $service 字典业务服务
     */
    public function __construct(
        protected DictService $service
    ) {}

    /**
     * 获取字典分页列表。
     */
    #[GetMapping(path: 'index')]
    #[Auth(name: '系统字典列表', type: Auth::CHECK, menu: true, code: 'system.dict.index')]
    public function index(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getPageList($request->all()));
    }

    /**
     * 获取字典回收站列表。
     */
    #[GetMapping(path: 'recycle')]
    #[Auth(name: '系统字典回收站', type: Auth::CHECK, menu: false, code: 'system.dict.index')]
    public function recycle(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getRecycleList($request->all()));
    }

    /**
     * 获取字典树。
     */
    #[GetMapping(path: 'tree')]
    #[Auth(name: '系统字典树', type: Auth::CHECK, menu: false, code: 'system.dict.index')]
    public function tree(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getTree($request->all()));
    }

    /**
     * 获取启用字典项选项。
     */
    #[GetMapping(path: 'options')]
    #[Auth(name: '获取字典选项', type: Auth::LOGIN, menu: false)]
    public function options(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getOptions($request->all()));
    }

    /**
     * 获取字典详情。
     */
    #[GetMapping(path: 'info/{id}')]
    #[Auth(name: '系统字典详情', type: Auth::CHECK, menu: false, code: 'system.dict.index')]
    public function info(int $id): array
    {
        $this->respondFound($this->service->read($id));
    }

    /**
     * 创建字典。
     */
    #[PostMapping(path: 'create')]
    #[Auth(name: '新增系统字典', type: Auth::CHECK, menu: false, code: 'system.dict.create')]
    #[Logger(name: '新增系统字典')]
    public function create(RequestInterface $request): array
    {
        $this->success('创建成功', $this->service->create($request->all()));
    }

    /**
     * 更新字典。
     */
    #[PutMapping(path: 'update/{id}')]
    #[Auth(name: '编辑系统字典', type: Auth::CHECK, menu: false, code: 'system.dict.update')]
    #[Logger(name: '编辑系统字典')]
    public function update(int $id, RequestInterface $request): array
    {
        $this->service->update($id, $request->all());
        $this->success('更新成功', $this->service->read($id));
    }

    /**
     * 删除字典（软删）。
     */
    #[DeleteMapping(path: 'delete/{ids}')]
    #[Auth(name: '删除系统字典', type: Auth::CHECK, menu: false, code: 'system.dict.delete')]
    #[Logger(name: '删除系统字典')]
    public function delete(string $ids): array
    {
        $this->deleteByIds($ids, fn (array $idArray) => $this->service->delete($idArray));
    }

    /**
     * 彻底删除字典。
     */
    #[DeleteMapping(path: 'real-delete/{ids}')]
    #[Auth(name: '彻底删除系统字典', type: Auth::CHECK, menu: false, code: 'system.dict.real-delete')]
    #[Logger(name: '彻底删除系统字典')]
    public function realDelete(string $ids): array
    {
        $this->deleteByIds($ids, fn (array $idArray) => $this->service->delreal($idArray), '彻底删除成功');
    }

    /**
     * 恢复字典。
     */
    #[PutMapping(path: 'recovery/{ids}')]
    #[Auth(name: '恢复系统字典', type: Auth::CHECK, menu: false, code: 'system.dict.recovery')]
    #[Logger(name: '恢复系统字典')]
    public function recovery(string $ids): array
    {
        $idArray = $this->idsOrFail($ids);
        $this->service->recovery($idArray);
        $this->success('恢复成功', $idArray);
    }

    /**
     * 更新字典状态。
     */
    #[PutMapping(path: 'status/{id}')]
    #[Auth(name: '更新字典状态', type: Auth::CHECK, menu: false, code: 'system.dict.update')]
    #[Logger(name: '更新字典状态')]
    public function changeStatus(int $id, RequestInterface $request): array
    {
        $this->success('更新成功', $this->service->changeStatus($id, $request->input('status', 1)));
    }

    /**
     * 更新字典排序。
     */
    #[PutMapping(path: 'sort/{id}')]
    #[Auth(name: '更新字典排序', type: Auth::CHECK, menu: false, code: 'system.dict.update')]
    #[Logger(name: '更新字典排序')]
    public function changeSort(int $id, RequestInterface $request): array
    {
        $this->success('更新成功', $this->service->changeSort($id, $request->input('sort', 0)));
    }

    /**
     * 获取字典统计。
     */
    #[GetMapping(path: 'statistics')]
    #[Auth(name: '字典统计', type: Auth::CHECK, menu: false, code: 'system.dict.index')]
    public function statistics(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getStatistics($request->all()));
    }
}
