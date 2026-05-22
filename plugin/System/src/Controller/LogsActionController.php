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
use System\Service\LogsActionService;

#[Auth(name: '操作日志管理')]
#[Controller(prefix: 'system/logs/action')]
final class LogsActionController extends CoreController
{
    /**
     * @param LogsActionService $service 日志业务服务
     */
    public function __construct(
        protected LogsActionService $service
    ) {}

    /**
     * 获取日志分页列表。
     */
    #[GetMapping(path: 'index')]
    #[Auth(name: '操作日志列表', type: Auth::CHECK, menu: true, code: 'system.logs.action.index')]
    public function index(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getPageList($request->all()));
    }

    /**
     * 获取日志回收站列表。
     */
    #[GetMapping(path: 'recycle')]
    #[Auth(name: '操作日志管理', type: Auth::CHECK, menu: false, code: 'system.logs.action.index')]
    public function recycle(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getRecycleList($request->all()));
    }

    /**
     * 获取日志详情。
     */
    #[GetMapping(path: 'info/{id}')]
    #[Auth(name: '操作日志详情', type: Auth::CHECK, menu: false, code: 'system.logs.action.index')]
    public function info(int $id): array
    {
        $this->respondFound($this->service->read($id));
    }

    /**
     * 删除日志（软删）。
     */
    #[DeleteMapping(path: 'delete/{ids}')]
    #[Auth(name: '删除操作日志', type: Auth::CHECK, menu: false, code: 'system.logs.action.delete')]
    #[Logger(name: '删除操作日志', code: 'system.logs.action.delete')]
    public function delete(string $ids): array
    {
        $this->deleteByIds($ids, fn (array $idArray) => $this->service->delete($idArray));
    }

    /**
     * 彻底删除日志。
     */
    #[DeleteMapping(path: 'real-delete/{ids}')]
    #[Auth(name: '彻底删除操作日志', type: Auth::CHECK, menu: false, code: 'system.logs.action.real-delete')]
    #[Logger(name: '彻底删除操作日志')]
    public function realDelete(string $ids): array
    {
        $this->deleteByIds($ids, fn (array $idArray) => $this->service->delreal($idArray), '彻底删除成功');
    }

    /**
     * 恢复日志。
     */
    #[PutMapping(path: 'recovery/{ids}')]
    #[Auth(name: '恢复操作日志', type: Auth::CHECK, menu: false, code: 'system.logs.action.recovery')]
    #[Logger(name: '恢复操作日志')]
    public function recovery(string $ids): array
    {
        $idArray = $this->idsOrFail($ids);
        $this->service->recovery($idArray);
        $this->success('恢复成功', $idArray);
    }

    /**
     * 清空日志，可选按天数过滤。
     */
    #[DeleteMapping(path: 'clear')]
    #[PostMapping(path: 'clear')]
    #[Auth(name: '清空操作日志', type: Auth::CHECK, menu: false, code: 'system.logs.action.clear')]
    #[Logger(name: '清空操作日志')]
    public function clear(RequestInterface $request): array
    {
        $days = $request->has('days') ? (int)$request->input('days') : null;
        $this->success('清空成功', $this->service->clear($days));
    }

    /**
     * 获取日志统计信息。
     */
    #[GetMapping(path: 'statistics')]
    #[Auth(name: '日志统计', type: Auth::CHECK, menu: false, code: 'system.logs.action.index')]
    public function statistics(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getStatistics($request->all()));
    }

    /**
     * 获取日志分析报告。
     */
    #[GetMapping(path: 'analysis')]
    #[Auth(name: '日志分析报告', type: Auth::CHECK, menu: false, code: 'system.logs.action.index')]
    public function analysis(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getAnalysisReport($request->all()));
    }

    /**
     * 获取日志近实时指标。
     */
    #[GetMapping(path: 'metrics')]
    #[Auth(name: '日志近实时指标', type: Auth::CHECK, menu: false, code: 'system.logs.action.index')]
    public function metrics(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getRealtimeMetrics($request->all()));
    }
}
