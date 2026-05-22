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
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Library\CoreController;
use Library\Events\Annotation\Auth;
use System\Service\LogsActionService;
use System\Service\LogsChangeService;

#[Auth(name: '变更日志管理')]
#[Controller(prefix: 'system/logs/change')]
final class LogsChangeController extends CoreController
{
    /**
     * @param LogsChangeService $service 变更日志服务
     * @param LogsActionService $actionService 操作日志服务，负责校验 action 可访问性并读取关联变更。
     */
    public function __construct(
        protected LogsChangeService $service,
        protected LogsActionService $actionService
    ) {}

    /**
     * 获取变更日志分页列表。
     */
    #[GetMapping(path: 'index')]
    #[Auth(name: '变更日志列表', type: Auth::CHECK, menu: true, code: 'system.logs.change.index')]
    public function index(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getPageList($request->all()));
    }

    /**
     * 获取变更日志详情。
     */
    #[GetMapping(path: 'info/{id}')]
    #[Auth(name: '变更日志详情', type: Auth::CHECK, menu: false, code: 'system.logs.change.index')]
    public function info(int $id): array
    {
        $this->respondFound($this->service->getDetail($id));
    }

    /**
     * 获取变更日志统计信息。
     */
    #[GetMapping(path: 'statistics')]
    #[Auth(name: '变更日志统计', type: Auth::CHECK, menu: false, code: 'system.logs.change.index')]
    public function statistics(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getStatistics($request->all()));
    }

    /**
     * 获取指定操作日志关联的业务变更明细。
     *
     * 操作日志详情使用该接口形成“操作行为 -> 数据变化”的闭环，读取权限复用操作日志详情权限。
     */
    #[GetMapping(path: 'action/{id}')]
    #[Auth(name: '操作日志变更明细', type: Auth::CHECK, menu: false, code: 'system.logs.action.index')]
    public function action(int $id): array
    {
        $this->success('获取成功', $this->actionService->getChanges($id));
    }
}
