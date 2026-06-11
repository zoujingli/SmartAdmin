<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace System\Service;

use Library\Constants\Status;
use Library\Exception\ErrorResponseException;
use Library\Support\TenantContext;
use System\Contract\ScheduledTaskExecutorInterface;
use System\Mapper\ScheduledTaskLogMapper;
use System\Mapper\ScheduledTaskMapper;
use System\Model\SystemScheduledTask;
use System\Support\Scheduler\ScheduledTaskRegistry;
use System\Support\Scheduler\ScheduleCalculator;

/**
 * 插件复用的调度计划服务。
 *
 * System 只提供共享调度内核；业务插件必须传入自己的 owner 边界，并在自身 Controller 中完成权限校验。
 */
final class PluginScheduledTaskService
{
    public function __construct(
        private readonly ScheduledTaskMapper $tasks,
        private readonly ScheduledTaskLogMapper $logs,
        private readonly ScheduledTaskRegistry $registry,
        private readonly ScheduledTaskExecutorInterface $executor,
    ) {}

    /**
     * @param array{owner_plugin:string,owner_type:string,owner_id:int,owner_name:string,code:string,name?:string,schedule_type:string,schedule_config:array<string,mixed>,params?:array<string,mixed>,timeout?:int,status?:int,remark?:string} $data
     */
    public function save(array $data): SystemScheduledTask
    {
        $tenantId = TenantContext::requireTenantId();
        $payload = $this->normalizePayload($data, $tenantId);
        $exists = $this->tasks->findByOwner(
            (string)$payload['owner_plugin'],
            (string)$payload['owner_type'],
            (int)$payload['owner_id'],
            (string)$payload['code'],
            true,
            $tenantId
        );

        if ($exists instanceof SystemScheduledTask) {
            if (method_exists($exists, 'trashed') && (bool)$exists->trashed()) {
                $exists->restore();
            }
            $this->tasks->updateOwned($exists, $payload);

            return $this->ownedTask(
                (string)$payload['owner_plugin'],
                (string)$payload['owner_type'],
                (int)$payload['owner_id'],
                (string)$payload['code'],
            );
        }

        /** @var SystemScheduledTask $created */
        $created = $this->tasks->create($payload);

        return $created;
    }

    public function readByOwner(string $ownerPlugin, string $ownerType, int $ownerId, string $code): ?SystemScheduledTask
    {
        return $this->tasks->findByOwner($ownerPlugin, $ownerType, $ownerId, $code, false, TenantContext::requireTenantId());
    }

    public function changeStatus(string $ownerPlugin, string $ownerType, int $ownerId, string $code, int $status): bool
    {
        if (!in_array($status, [Status::ENABLED, Status::DISABLED], true)) {
            throw new ErrorResponseException('状态值错误');
        }
        $task = $this->ownedTask($ownerPlugin, $ownerType, $ownerId, $code);

        return $this->tasks->updateOwned($task, [
            'status' => $status,
            'next_run_at' => $status === Status::ENABLED
                ? ScheduleCalculator::nextRunAt((string)$task->schedule_type, is_array($task->schedule_config) ? $task->schedule_config : [])
                : $task->next_run_at,
        ]);
    }

    public function delete(string $ownerPlugin, string $ownerType, int $ownerId, string $code): bool
    {
        $task = $this->ownedTask($ownerPlugin, $ownerType, $ownerId, $code);

        return $this->tasks->deleteOwned($task);
    }

    public function run(string $ownerPlugin, string $ownerType, int $ownerId, string $code): array
    {
        $task = $this->ownedTask($ownerPlugin, $ownerType, $ownerId, $code);
        $claimed = $this->tasks->claim((int)$task->id, date('Y-m-d H:i:s', time() + max(60, (int)$task->timeout)));
        if (!$claimed instanceof SystemScheduledTask) {
            throw new ErrorResponseException('任务正在执行，请稍后再试');
        }

        return $this->executor->execute($claimed, true);
    }

    public function logs(string $ownerPlugin, string $ownerType, int $ownerId, string $code, array $params = []): array
    {
        // 日志列表依赖模型租户范围；这里显式要求登录租户上下文，保证插件入口 fail closed。
        TenantContext::requireTenantId();

        return $this->logs->getPageList([
            'page' => $params['page'] ?? 1,
            'pageSize' => $params['pageSize'] ?? $params['page_size'] ?? 10,
            'keyword' => $params['keyword'] ?? '',
            'status' => $params['status'] ?? null,
            'trigger_type' => $params['trigger_type'] ?? null,
            'owner_plugin' => $ownerPlugin,
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
            'task_code' => $code,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizePayload(array $data, int $tenantId): array
    {
        $ownerPlugin = trim((string)($data['owner_plugin'] ?? ''));
        $ownerType = trim((string)($data['owner_type'] ?? ''));
        $ownerId = max(0, (int)($data['owner_id'] ?? 0));
        $code = trim((string)($data['code'] ?? ''));
        if ($ownerPlugin === '' || $ownerType === '' || $code === '') {
            throw new ErrorResponseException('任务归属和任务编码不能为空');
        }
        $definition = $this->registry->get($code);
        if ($definition === null || $definition->ownerPlugin !== $ownerPlugin) {
            throw new ErrorResponseException('任务定义不存在或不属于当前插件');
        }

        $type = (string)($data['schedule_type'] ?? ScheduleCalculator::TYPE_DAILY);
        $config = ScheduleCalculator::normalize($type, is_array($data['schedule_config'] ?? null) ? $data['schedule_config'] : []);
        $status = array_key_exists('status', $data) ? (int)$data['status'] : Status::ENABLED;
        if (!in_array($status, [Status::ENABLED, Status::DISABLED], true)) {
            throw new ErrorResponseException('状态值错误');
        }

        return [
            'tenant_id' => $tenantId,
            'owner_plugin' => $ownerPlugin,
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
            'owner_name' => mb_substr(trim((string)($data['owner_name'] ?? '')), 0, 120) ?: '业务任务',
            'code' => $code,
            'group_name' => $definition->group,
            'name' => mb_substr(trim((string)($data['name'] ?? $definition->name)), 0, 120),
            'schedule_type' => $type,
            'schedule_config' => $config,
            'params' => is_array($data['params'] ?? null) ? $data['params'] : [],
            'timeout' => max(1, min(86400, (int)($data['timeout'] ?? $definition->timeout))),
            'status' => $status,
            'next_run_at' => ScheduleCalculator::nextRunAt($type, $config),
            'remark' => mb_substr(trim((string)($data['remark'] ?? '')), 0, 1000),
        ];
    }

    private function ownedTask(string $ownerPlugin, string $ownerType, int $ownerId, string $code): SystemScheduledTask
    {
        $task = $this->readByOwner($ownerPlugin, $ownerType, $ownerId, $code);
        if (!$task instanceof SystemScheduledTask) {
            throw new ErrorResponseException('定时任务计划不存在');
        }

        return $task;
    }
}
