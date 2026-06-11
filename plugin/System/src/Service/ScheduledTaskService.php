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
use System\Support\Scheduler\ScheduledTaskRegistry;
use System\Support\Scheduler\ScheduleCalculator;

final class ScheduledTaskService extends CoreService
{
    public function __construct(
        protected ScheduledTaskMapper $mapper,
        private readonly ScheduledTaskRegistry $registry,
        private readonly ScheduledTaskExecutorInterface $executor,
    ) {}

    public function options(): array
    {
        return [
            'tasks' => $this->registry->options(),
            'schedule_types' => ScheduleCalculator::types(),
        ];
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
            true
        );

        if ($trashed instanceof SystemScheduledTask && method_exists($trashed, 'trashed') && (bool)$trashed->trashed()) {
            $trashed->restore();
            $this->mapper->update($trashed, $payload);

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
        $this->ensureSystemOwned($task);

        return $this->mapper->update($task, $this->filterData($data, $task->toArray()));
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
            $this->ensureSystemOwned($task);
        }

        return $this->mapper->delete($ids);
    }

    public function run(int $id): array
    {
        $task = $this->mapper->read($id);
        if (!$task instanceof SystemScheduledTask) {
            throw new ErrorResponseException('任务不存在');
        }
        $this->ensureSystemOwned($task);

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
        $this->ensureSystemOwned($task);
        if (!in_array($status, [Status::ENABLED, Status::DISABLED], true)) {
            throw new ErrorResponseException('状态值错误');
        }

        return $this->mapper->update($task, [
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
            'name.filled' => '任务名称不能为空',
            'name.max:120' => '任务名称最多 120 位',
            'schedule_type.filled' => '请选择周期类型',
            'schedule_type.in:every_minutes,hourly,daily,weekly,monthly' => '周期类型不支持',
            'timeout.integer' => '超时时间必须为数字',
            'timeout.min:1' => '超时时间不能小于 1 秒',
            'timeout.max:86400' => '超时时间不能超过 86400 秒',
            'status.integer' => '状态必须为数字',
            'status.in:1,0' => '状态值错误',
            'remark.max:1000' => '备注最多 1000 位',
        ];
        if ($exists === []) {
            $rules['code.required'] = '请选择任务';
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
        if ($definition->ownerPlugin !== 'system') {
            throw new ErrorResponseException('业务插件任务请在对应子系统中管理');
        }

        $data['tenant_id'] = (int)($exists['tenant_id'] ?? TenantContext::requireTenantId());
        $data['owner_plugin'] = 'system';
        $data['owner_type'] = 'system';
        $data['owner_id'] = 0;
        $data['owner_name'] = '系统任务';
        $data['code'] = $code;
        $data['group_name'] = $definition->group;
        $data['name'] = trim((string)($data['name'] ?? $exists['name'] ?? $definition->name));
        $data['timeout'] = max(1, (int)($data['timeout'] ?? $exists['timeout'] ?? $definition->timeout));
        $type = (string)($data['schedule_type'] ?? $exists['schedule_type'] ?? ScheduleCalculator::TYPE_DAILY);
        $config = ScheduleCalculator::normalize($type, $inputScheduleConfig ?? (array)($exists['schedule_config'] ?? []));
        $data['schedule_type'] = $type;
        $data['schedule_config'] = $config;
        $data['next_run_at'] = ScheduleCalculator::nextRunAt($type, $config);
        $data['params'] = $inputParams ?? (array)($exists['params'] ?? []);

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

    private function ensureSystemOwned(SystemScheduledTask $task): void
    {
        if ((string)($task->owner_plugin ?: 'system') !== 'system' || (string)($task->owner_type ?: 'system') !== 'system' || (int)$task->owner_id !== 0) {
            throw new ErrorResponseException('业务插件任务请在对应子系统中管理');
        }
    }
}
