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
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Library\CoreController;
use Library\Events\Annotation\Auth;
use System\Service\ScheduledTaskLogService;

#[Auth(name: '定时任务日志')]
#[Controller(prefix: 'system/scheduler/log')]
final class ScheduledTaskLogController extends CoreController
{
    public function __construct(
        protected ScheduledTaskLogService $service,
    ) {}

    #[GetMapping(path: 'index')]
    #[Auth(name: '定时任务日志列表', type: Auth::CHECK, menu: false, code: 'system.scheduler.log.index')]
    public function index(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getPageList($request->all()));
    }

    #[GetMapping(path: 'info/{id}')]
    #[Auth(name: '定时任务日志详情', type: Auth::CHECK, menu: false, code: 'system.scheduler.log.index')]
    public function info(int $id): array
    {
        $this->respondFound($this->service->read($id));
    }
}
