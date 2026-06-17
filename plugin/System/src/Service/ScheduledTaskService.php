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

use Hyperf\Database\Model\Model;
use Library\Constants\Status;
use Library\CoreService;
use Library\Exception\ErrorResponseException;
use Library\Support\TenantContext;
use System\Contract\ScheduledTaskExecutorInterface;
use System\Mapper\ScheduledTaskMapper;
use System\Model\SystemScheduledTask;
use System\Support\Scheduler\ScheduleCalculator;
use System\Support\Scheduler\ScheduledTaskOwnerRegistry;
use System\Support\Scheduler\ScheduledTaskRegistry;

final class ScheduledTaskService extends CoreService
{
    public function __construct(
        protected ScheduledTaskMapper $mapper,
        private readonly ScheduledTaskRegistry $registry,
        private readonly ScheduledTaskOwnerRegistry $owners,
        private readonly ScheduledTaskExecutorInterface $executor,
    ) {}

    public function options(): array
    {
        return [
            'tasks' => $this->registry->options(),
            'owner_types' => $this->owners->types('system'),
            'schedule_types' => ScheduleCalculator::types(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function ownerTypes(string $ownerPlugin): array
    {
        $ownerPlugin = trim($ownerPlugin);
        if ($ownerPlugin === '') {
            throw new ErrorResponseException('任务归属插件不能为空');
        }

        return $this->owners->types($ownerPlugin);
    }

    /**
     * @return array{items:list<array<string,mixed>>,pageInfo:array<string,int>}
     */
    public function ownerOptions(array $params): array
    {
        $ownerPlugin = trim((string)($params['owner_plugin'] ?? ''));
        $ownerType = trim((string)($params['owner_type'] ?? ''));
        if ($ownerPlugin === '' || $ownerType === '') {
            throw new ErrorResponseException('任务归属类型不能为空');
        }

        return $this->owners->options($ownerPlugin, $ownerType, $params);
    }

    public function runtime(): array
    {
        return [
            'process' => 'system.scheduler',
            'default_enabled' => true,
            'switch_env' => null,
            'registered_task_count' => count($this->registry->all()),
            'server_time' => date('Y-m-d H:i:s'),
        ];
    }

    public function create(array $data): ?Model
    {
        $payload = $this->filterData($data);
        $trashed = $this->mapper->findByOwner(
            (string)$payload['owner_plugin'],
            (string)$payload['owner_type'],
            (int)$payload['owner_id'],
            (string)$payload['code'],
            true,
            (int)$payload['tenant_id']
        );

        if ($trashed instanceof SystemScheduledTask && method_exists($trashed, 'trashed') && (bool)$trashed->trashed()) {
            $trashed->restore();
            $this->mapper->updateOwned($trashed, $payload);

            return $this->mapper->read((int)$trashed->id);
        }

        return $this->mapper->create($payload);
    }

    public function update(mixed $id, array $data): bool
    {
        $task = $this->mapper->read($id);
        if (!$task instanceof SystemScheduledTask) {
            throw new ErrorResponseException('任务不存在');
        }
        $this->ensureOwnerManageable($task);
        $this->ensureNotRunning($task);

        return $this->mapper->updateOwned($task, $this->filterData($data, $task->toArray()));
    }

    public function delete(array $ids): bool
    {
        $tasks = $this->mapper->getOperationModels($ids);
        if (count($tasks) !== count(array_unique(array_map('intval', $ids)))) {
            return false;
        }
        foreach ($tasks as $task) {
            if (!$task instanceof SystemScheduledTask) {
                return false;
            }
            $this->ensureOwnerManageable($task);
            $this->ensureNotRunning($task);
        }

        foreach ($tasks as $task) {
            if (!$this->mapper->deleteOwned($task)) {
                return false;
            }
        }

        return true;
    }

    public function run(int $id): array
    {
        $task = $this->mapper->read($id);
        if (!$task instanceof SystemScheduledTask) {
            throw new ErrorResponseException('任务不存在');
        }
        $this->ensureOwnerManageable($task);
        if ((int)$task->status !== Status::ENABLED) {
            throw new ErrorResponseException('任务已禁用，不能立即执行');
        }

        $claimed = $this->mapper->claim((int)$task->id, date('Y-m-d H:i:s', time() + max(60, (int)$task->timeout)));
        if (!$claimed instanceof SystemScheduledTask) {
            throw new ErrorResponseException('任务正在执行，请稍后再试');
        }

        return $this->executor->execute($claimed, true);
    }

    public function changeStatus(int $id, int $status): bool
    {
        $task = $this->mapper->read($id);
        if (!$task instanceof SystemScheduledTask) {
            throw new ErrorResponseException('任务不存在');
        }
        $this->ensureOwnerManageable($task);
        if (!in_array($status, [Status::ENABLED, Status::DISABLED], true)) {
            throw new ErrorResponseException('状态值错误');
        }

        return $this->mapper->updateOwned($task, [
            'status' => $status,
            'next_run_at' => $status === Status::ENABLED
                ? ScheduleCalculator::nextRunAt((string)$task->schedule_type, is_array($task->schedule_config) ? $task->schedule_config : [])
                : $task->next_run_at,
        ]);
    }

    protected function filterData(array &$data, array $exists = []): array
    {
        $inputScheduleConfig = $this->normalizeJsonInput($data, 'schedule_config');
        $inputParams = $this->normalizeJsonInput($data, 'params');

        $rules = [
            'code.filled' => '请选择任务',
            'code.max:120' => '任务编码最多 120 位',
            'owner_plugin.filled' => '任务归属插件不能为空',
            'owner_plugin.max:120' => '任务归属插件最多 120 位',
            'owner_type.filled' => '请选择任务归属类型',
            'owner_type.max:120' => '任务归属类型最多 120 位',
            'owner_id.integer' => '任务归属资源必须为数字',
            'owner_id.min:0' => '任务归属资源无效',
            'name.filled' => '任务名称不能为空',
            'name.max:120' => '任务名称最多 120 位',
            'schedule_type.filled' => '请选择周期类型',
            'schedule_type.in:every_seconds,every_minutes,every_hours,hourly,daily,weekly,monthly' => '周期类型不支持',
            'timeout.integer' => '超时时间必须为数字',
            'timeout.min:1' => '超时时间不能小于 1 秒',
            'timeout.max:86400' => '超时时间不能超过 86400 秒',
            'status.integer' => '状态必须为数字',
            'status.in:1,0' => '状态值错误',
            'remark.max:1000' => '备注最多 1000 位',
        ];
        if ($exists === []) {
            $rules['code.required'] = '请选择任务';
            $rules['owner_type.required'] = '请选择任务归属类型';
            $rules['owner_id.default'] = 0;
            $rules['name.required'] = '任务名称不能为空';
            $rules['schedule_type.default'] = ScheduleCalculator::TYPE_DAILY;
            $rules['schedule_config.default'] = [];
            $rules['params.default'] = [];
            $rules['timeout.default'] = 3600;
            $rules['status.default'] = Status::ENABLED;
        }

        $data = _vali($rules, $data);
        $code = (string)($data['code'] ?? $exists['code'] ?? '');
        $definition = $this->registry->get($code);
        if ($definition === null) {
            throw new ErrorResponseException('任务定义不存在或插件未启用');
        }

        $data['tenant_id'] = (int)($exists['tenant_id'] ?? TenantContext::requireTenantId());
        if ($exists === []) {
            $ownerPlugin = $definition->ownerPlugin;
            $ownerType = trim((string)($data['owner_type'] ?? ($ownerPlugin === 'system' ? 'system' : '')));
            $ownerId = (int)($data['owner_id'] ?? 0);
            if ($ownerType === '') {
                throw new ErrorResponseException('请选择任务归属类型');
            }
            // System 是全局运维入口，但业务任务 owner 必须由插件解析器确认并补齐派生参数。
            $owner = $this->owners->resolve($ownerPlugin, $ownerType, $ownerId, $data);
            if ($owner->ownerPlugin !== $ownerPlugin) {
                throw new ErrorResponseException('任务归属插件与任务定义不匹配');
            }
            $data['owner_plugin'] = $owner->ownerPlugin;
            $data['owner_type'] = $owner->ownerType;
            $data['owner_id'] = $owner->ownerId;
            $data['owner_name'] = $owner->ownerName;
            $resolverParams = $owner->params;
        } else {
            $this->ensureImmutableOwner($data, $exists);
            $data['owner_plugin'] = (string)$exists['owner_plugin'];
            $data['owner_type'] = (string)$exists['owner_type'];
            $data['owner_id'] = (int)$exists['owner_id'];
            // 编辑已有计划时重新解析当前 owner，保证业务资源仍存在，并把 resolver 派生参数重新补回。
            $owner = $this->owners->resolve($data['owner_plugin'], $data['owner_type'], $data['owner_id'], $data);
            $data['owner_name'] = $owner->ownerName;
            $resolverParams = $owner->params;
        }
        $data['code'] = $code;
        $data['group_name'] = $definition->group;
        $data['name'] = trim((string)($data['name'] ?? $exists['name'] ?? $definition->name));
        $data['timeout'] = max(1, (int)($data['timeout'] ?? $exists['timeout'] ?? $definition->timeout));
        $type = (string)($data['schedule_type'] ?? $exists['schedule_type'] ?? ScheduleCalculator::TYPE_DAILY);
        $config = ScheduleCalculator::normalize($type, $inputScheduleConfig ?? (array)($exists['schedule_config'] ?? []));
        $data['schedule_type'] = $type;
        $data['schedule_config'] = $config;
        $data['next_run_at'] = ScheduleCalculator::nextRunAt($type, $config);
        $data['params'] = [
            ...($inputParams ?? (array)($exists['params'] ?? [])),
            ...$resolverParams,
        ];

        $this->ensureUniqueCode($data, $exists);

        return $data;
    }

    private function normalizeJsonInput(array &$data, string $field): ?array
    {
        if (!array_key_exists($field, $data)) {
            return null;
        }
        if (is_array($data[$field])) {
            return $data[$field];
        }
        if (!is_string($data[$field])) {
            return [];
        }

        $decoded = json_decode($data[$field], true);
        return is_array($decoded) ? $decoded : [];
    }

    private function ensureUniqueCode(array $data, array $exists): void
    {
        $query = SystemScheduledTask::query()
            ->where('tenant_id', (int)$data['tenant_id'])
            ->where('owner_plugin', (string)$data['owner_plugin'])
            ->where('owner_type', (string)$data['owner_type'])
            ->where('owner_id', (int)$data['owner_id'])
            ->where('code', (string)$data['code']);
        if (!empty($exists['id'])) {
            $query->where('id', '!=', (int)$exists['id']);
        }
        if ($query->exists()) {
            throw new ErrorResponseException('该任务已存在，请直接编辑原计划');
        }
    }

    private function ensureImmutableOwner(array $data, array $exists): void
    {
        // 已有计划的任务定义和 owner 共同决定执行上下文，System 编辑时只允许调整计划规则。
        foreach (['code', 'owner_plugin', 'owner_type', 'owner_id'] as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            if ((string)$data[$field] !== (string)($exists[$field] ?? '')) {
                throw new ErrorResponseException('任务类型和归属不能直接修改，请删除后重新创建');
            }
        }
    }

    private function ensureOwnerManageable(SystemScheduledTask $task): void
    {
        $ownerPlugin = (string)($task->owner_plugin ?: 'system');
        $ownerType = (string)($task->owner_type ?: 'system');
        if (!$this->owners->has($ownerPlugin, $ownerType)) {
            throw new ErrorResponseException('任务归属解析器不存在，只能查看该业务任务');
        }
    }

    private function ensureNotRunning(SystemScheduledTask $task): void
    {
        if ((int)$task->running === 1) {
            throw new ErrorResponseException('任务正在执行，请稍后再操作');
        }
    }
}
