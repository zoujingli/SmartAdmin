<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace System\Mapper;

use Hyperf\Database\Model\Builder;
use Hyperf\DbConnection\Db;
use Library\Constants\DataField;
use Library\Constants\Status;
use Library\CoreMapper;
use Library\Support\TenantContext;
use System\Model\SystemScheduledTask;

final class ScheduledTaskMapper extends CoreMapper
{
    public function __construct(
        protected string $model = SystemScheduledTask::class
    ) {}

    /**
     * 调度进程没有登录态，扫描时必须显式移除租户全局范围；执行前会按任务 tenant_id 恢复上下文。
     *
     * @return array<int, SystemScheduledTask>
     */
    public function dueTasks(int $limit = 20): array
    {
        return SystemScheduledTask::query()
            ->withoutGlobalScope(DataField::TENANT)
            ->where('tenant_id', '>', 0)
            ->where('status', Status::ENABLED)
            ->where('next_run_at', '<=', date('Y-m-d H:i:s'))
            ->where(function (Builder $query): void {
                $query->where('running', 0)
                    ->orWhereNull('locked_until')
                    ->orWhere('locked_until', '<', date('Y-m-d H:i:s'));
            })
            ->orderBy('next_run_at')
            ->limit(max(1, $limit))
            ->get()
            ->all();
    }

    public function markInvalidDueTasks(int $limit = 20): int
    {
        // 只处理已经到期的脏租户任务，避免每轮调度全表扫描；修复后仍可按新租户重新配置计划。
        $ids = Db::table('system_scheduled_task')
            ->where('status', Status::ENABLED)
            ->where('next_run_at', '<=', date('Y-m-d H:i:s'))
            ->where(function ($query): void {
                $query->whereNull('tenant_id')->orWhere('tenant_id', '<=', 0);
            })
            ->limit(max(1, $limit))
            ->pluck('id')
            ->toArray();
        if ($ids === []) {
            return 0;
        }

        return Db::table('system_scheduled_task')
            ->whereIn('id', array_map('intval', $ids))
            ->update([
                'running' => 0,
                'locked_until' => null,
                'lock_token' => '',
                'last_run_at' => date('Y-m-d H:i:s'),
                'last_status' => SystemScheduledTask::STATUS_FAILED,
                'last_message' => '任务租户上下文无效，已跳过执行',
                'next_run_at' => date('Y-m-d H:i:s', time() + 3600),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    public function claim(int $taskId, string $lockedUntil): ?SystemScheduledTask
    {
        return Db::transaction(function () use ($taskId, $lockedUntil): ?SystemScheduledTask {
            $task = SystemScheduledTask::query()
                ->withoutGlobalScope(DataField::TENANT)
                ->where('id', $taskId)
                ->lockForUpdate()
                ->first();
            if (!$task instanceof SystemScheduledTask || (int)$task->status !== Status::ENABLED) {
                return null;
            }
            if ((int)$task->tenant_id <= 0) {
                $this->markInvalidTenant($task);
                return null;
            }
            if ((int)$task->running === 1 && (string)($task->locked_until ?? '') >= date('Y-m-d H:i:s')) {
                return null;
            }

            $token = $this->makeLockToken($taskId);
            TenantContext::withTenant((int)$task->tenant_id, static fn (): bool => $task->update([
                'running' => 1,
                'locked_until' => $lockedUntil,
                'lock_token' => $token,
                'last_status' => SystemScheduledTask::STATUS_RUNNING,
            ]));

            return $task->refresh();
        });
    }

    public function release(SystemScheduledTask $task, array $data): bool
    {
        $query = Db::table('system_scheduled_task')
            ->where('id', (int)$task->id)
            ->where('lock_token', (string)$task->lock_token);

        return $query->update([
            ...$data,
            'running' => 0,
            'locked_until' => null,
            'lock_token' => '',
            'updated_at' => date('Y-m-d H:i:s'),
        ]) > 0;
    }

    public function heartbeat(SystemScheduledTask $task, string $lockedUntil): bool
    {
        // 续期必须持有同一个 lock_token，避免旧执行实例延长新实例的锁。
        return Db::table('system_scheduled_task')
            ->where('id', (int)$task->id)
            ->where('lock_token', (string)$task->lock_token)
            ->where('running', 1)
            ->update([
                'locked_until' => $lockedUntil,
                'updated_at' => date('Y-m-d H:i:s'),
            ]) > 0;
    }

    public function markInvalidTenant(SystemScheduledTask $task): void
    {
        // 历史脏数据不能中断整轮调度；标记失败并推迟下次扫描，等待租户修复命令处理。
        Db::table('system_scheduled_task')
            ->where('id', (int)$task->id)
            ->update([
                'running' => 0,
                'locked_until' => null,
                'lock_token' => '',
                'last_run_at' => date('Y-m-d H:i:s'),
                'last_status' => SystemScheduledTask::STATUS_FAILED,
                'last_message' => '任务租户上下文无效，已跳过执行',
                'next_run_at' => date('Y-m-d H:i:s', time() + 3600),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    public function updateOwned(SystemScheduledTask $task, array $data): bool
    {
        // 插件计划的操作边界是 tenant_id + owner_*，不能再叠加 System 后台 created_by 数据范围。
        return (bool)TenantContext::withTenant((int)$task->tenant_id, fn (): bool => $task->update($this->filterModelData($data, true)));
    }

    public function deleteOwned(SystemScheduledTask $task): bool
    {
        // 插件入口已通过 owner 定位业务资源；这里仅保留模型软删除语义，不使用 CoreMapper 操作范围。
        return (bool)TenantContext::withTenant((int)$task->tenant_id, fn (): ?bool => $task->delete());
    }

    public function findByOwner(string $ownerPlugin, string $ownerType, int $ownerId, string $code, bool $withTrashed = false, ?int $tenantId = null): ?SystemScheduledTask
    {
        $query = $withTrashed ? SystemScheduledTask::withTrashed() : SystemScheduledTask::query();
        if ($tenantId !== null) {
            // 插件规则查询必须把租户作为显式边界，避免 owner 字段相同的跨租户计划被误读。
            $tenantId > 0
                ? $query->withoutGlobalScope(DataField::TENANT)->where('tenant_id', $tenantId)
                : $query->whereRaw('1 = 0');
        }

        return $query
            ->where('owner_plugin', $ownerPlugin)
            ->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->where('code', $code)
            ->first();
    }

    protected function handleSearch(Builder $query, array $params): Builder
    {
        $builder = _query($query, $params)
            ->like('name,code,group_name,owner_plugin,owner_type,owner_name,remark')
            ->equal('status,schedule_type,last_status,code,owner_plugin,owner_type,owner_id')
            ->dateBetween('created_at')
            ->getQuery();

        $keyword = trim((string)($params['keyword'] ?? ''));
        if ($keyword !== '') {
            $builder->where(function (Builder $subQuery) use ($keyword): void {
                $like = "%{$keyword}%";
                $subQuery->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like)
                    ->orWhere('group_name', 'like', $like)
                    ->orWhere('owner_name', 'like', $like)
                    ->orWhere('remark', 'like', $like);
            });
        }

        return $builder;
    }

    protected function makeFilteredQuery(?array $params, bool $isScope): Builder
    {
        $params ??= [];
        $modelClass = $this->model;
        $query = ($params['recycle'] ?? false) === true ? $modelClass::onlyTrashed() : $modelClass::query();

        return $this->handleSearch($query, $params);
    }

    protected function applyOperationScope(Builder $query): Builder
    {
        // 任务管理是租户级运维入口，列表和详情按租户隔离；业务插件任务在 System 页面只读展示，写操作由 Service 的 owner 边界限制。
        return $query;
    }

    private function makeLockToken(int $taskId): string
    {
        return sprintf('%d:%s:%s', $taskId, str_replace('.', '', uniqid('', true)), bin2hex(random_bytes(8)));
    }
}
