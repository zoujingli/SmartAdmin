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
use System\Service\ScheduledTaskService;

#[Auth(name: '定时任务管理')]
#[Controller(prefix: 'system/scheduler/task')]
final class ScheduledTaskController extends CoreController
{
    public function __construct(
        protected ScheduledTaskService $service,
    ) {}

    #[GetMapping(path: 'index')]
    #[Auth(name: '定时任务列表', type: Auth::CHECK, menu: true, code: 'system.scheduler.task.index')]
    public function index(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getPageList($request->all()));
    }

    #[GetMapping(path: 'info/{id}')]
    #[Auth(name: '定时任务详情', type: Auth::CHECK, menu: false, code: 'system.scheduler.task.index')]
    public function info(int $id): array
    {
        $this->respondFound($this->service->read($id));
    }

    #[PostMapping(path: 'create')]
    #[Auth(name: '新增定时任务', type: Auth::CHECK, menu: false, code: 'system.scheduler.task.create')]
    #[Logger(name: '新增定时任务')]
    public function create(RequestInterface $request): array
    {
        $this->success('创建成功', $this->service->create($request->all()));
    }

    #[PutMapping(path: 'update/{id}')]
    #[Auth(name: '编辑定时任务', type: Auth::CHECK, menu: false, code: 'system.scheduler.task.update')]
    #[Logger(name: '编辑定时任务')]
    public function update(int $id, RequestInterface $request): array
    {
        $this->service->update($id, $request->all());
        $this->success('更新成功', $this->service->read($id));
    }

    #[PutMapping(path: 'status/{id}')]
    #[Auth(name: '更新定时任务状态', type: Auth::CHECK, menu: false, code: 'system.scheduler.task.status')]
    #[Logger(name: '更新定时任务状态')]
    public function changeStatus(int $id, RequestInterface $request): array
    {
        $this->success('更新成功', $this->service->changeStatus($id, (int)$request->input('status', 1)));
    }

    #[PostMapping(path: 'run/{id}')]
    #[Auth(name: '立即执行定时任务', type: Auth::CHECK, menu: false, code: 'system.scheduler.task.run')]
    #[Logger(name: '立即执行定时任务')]
    public function run(int $id): array
    {
        $this->success('执行完成', $this->service->run($id));
    }

    #[DeleteMapping(path: 'delete/{ids}')]
    #[Auth(name: '删除定时任务', type: Auth::CHECK, menu: false, code: 'system.scheduler.task.delete')]
    #[Logger(name: '删除定时任务')]
    public function delete(string $ids): array
    {
        $this->deleteByIds($ids, fn (array $idArray) => $this->service->delete($idArray));
    }

    #[GetMapping(path: 'options')]
    #[Auth(name: '定时任务选项', type: Auth::CHECK, menu: false, code: 'system.scheduler.task.index')]
    public function options(): array
    {
        $this->success('获取成功', $this->service->options());
    }

    #[GetMapping(path: 'runtime')]
    #[Auth(name: '定时任务运行时', type: Auth::CHECK, menu: false, code: 'system.scheduler.task.index')]
    public function runtime(): array
    {
        $this->success('获取成功', $this->service->runtime());
    }
}
