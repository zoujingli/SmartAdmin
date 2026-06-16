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

use Hyperf\DbConnection\Db;
use Library\Constants\DataField;
use Library\Support\TenantContext;
use Swoole\Coroutine;
use System\Contract\ScheduledTaskExecutorInterface;
use System\Mapper\ScheduledTaskMapper;
use System\Model\SystemScheduledTask;
use System\Model\SystemScheduledTaskLog;
use System\Support\Scheduler\ScheduleCalculator;
use System\Support\Scheduler\ScheduledTaskContext;
use System\Support\Scheduler\ScheduledTaskRegistry;

/**
 * v1 进程内执行器。
 *
 * 调度和执行通过接口隔离，后续如引入 queue 只需替换执行器，不影响后台计划管理和白名单任务定义。
 */
final class ScheduledTaskExecutor implements ScheduledTaskExecutorInterface
{
    private const RESULT_MAX_BYTES = 20000;

    private const RESULT_MAX_DEPTH = 5;

    private const RESULT_MAX_ITEMS = 50;

    public function __construct(
        private readonly ScheduledTaskRegistry $registry,
        private readonly ScheduledTaskMapper $tasks,
    ) {}

    public function execute(SystemScheduledTask $task, bool $manual = false): array
    {
        $definition = $this->registry->get((string)$task->code);
        if ($definition === null) {
            $message = '任务定义不存在或插件未启用';
            $this->tasks->release($task, [
                'last_run_at' => date('Y-m-d H:i:s'),
                'last_status' => SystemScheduledTask::STATUS_FAILED,
                'last_message' => $message,
                'next_run_at' => $this->nextRunAt($task),
            ]);

            return ['status' => SystemScheduledTask::STATUS_FAILED, 'message' => $message];
        }

        $started = microtime(true);
        $startedAt = date('Y-m-d H:i:s');
        try {
            $log = $this->createLog($task, $manual, $startedAt);
        } catch (\Throwable $exception) {
            $message = mb_substr('执行日志创建失败：' . $exception->getMessage(), 0, 2000);
            $this->tasks->release($task, [
                'last_run_at' => date('Y-m-d H:i:s'),
                'last_status' => SystemScheduledTask::STATUS_FAILED,
                'last_message' => $message,
                'next_run_at' => $this->nextRunAt($task),
            ]);
            _trace($exception);

            return ['status' => SystemScheduledTask::STATUS_FAILED, 'message' => $message];
        }

        $status = SystemScheduledTask::STATUS_SUCCESS;
        $message = '执行成功';
        $result = [];
        $heartbeat = $this->startHeartbeat($task);

        try {
            $context = new ScheduledTaskContext(
                taskId: (int)$task->id,
                logId: (int)$log->id,
                taskCode: (string)$task->code,
                tenantId: (int)$task->tenant_id,
                ownerPlugin: (string)($task->owner_plugin ?: 'system'),
                ownerType: (string)($task->owner_type ?: 'system'),
                ownerId: (int)$task->owner_id,
                ownerName: (string)($task->owner_name ?: '系统任务'),
                params: is_array($task->params) ? $task->params : [],
                manual: $manual,
            );

            $handler = $this->registry->handler($definition);
            $result = TenantContext::withTenant((int)$task->tenant_id, fn (): array => $handler->handle($context));
        } catch (\Throwable $exception) {
            $status = SystemScheduledTask::STATUS_FAILED;
            $message = mb_substr($exception->getMessage(), 0, 2000);
            $result = ['exception' => get_class($exception)];
            _trace($exception);
        } finally {
            $heartbeat['running'] = false;
        }

        $durationMs = (int)round((microtime(true) - $started) * 1000);
        $finishedAt = date('Y-m-d H:i:s');
        $result = $this->sanitizeResult($result);
        try {
            $this->finishLog($log, $status, $message, $result, $finishedAt, $durationMs);
        } catch (\Throwable $exception) {
            _trace($exception);
            $message = mb_substr($message . '；执行日志更新失败：' . $exception->getMessage(), 0, 2000);
        }

        $this->tasks->release($task, [
            'last_run_at' => $finishedAt,
            'last_status' => $status,
            'last_message' => $message,
            'next_run_at' => $this->nextRunAt($task, $finishedAt),
        ]);

        return [
            'status' => $status,
            'message' => $message,
            'duration_ms' => $durationMs,
            'result' => $result,
        ];
    }

    private function createLog(SystemScheduledTask $task, bool $manual, string $startedAt): SystemScheduledTaskLog
    {
        // 调度进程没有后台登录态，执行日志必须直写已抢占任务的租户，不能触发模型监听器重新解析请求用户。
        $id = Db::table('system_scheduled_task_log')->insertGetId([
            'tenant_id' => (int)$task->tenant_id,
            'task_id' => (int)$task->id,
            'owner_plugin' => (string)($task->owner_plugin ?: 'system'),
            'owner_type' => (string)($task->owner_type ?: 'system'),
            'owner_id' => (int)$task->owner_id,
            'owner_name' => (string)($task->owner_name ?: '系统任务'),
            'task_code' => (string)$task->code,
            'task_name' => (string)$task->name,
            'trigger_type' => $manual ? SystemScheduledTaskLog::TRIGGER_MANUAL : SystemScheduledTaskLog::TRIGGER_AUTO,
            'status' => SystemScheduledTask::STATUS_RUNNING,
            'message' => '',
            'result' => '[]',
            'started_at' => $startedAt,
            'created_at' => $startedAt,
            'updated_at' => $startedAt,
        ]);

        /** @var SystemScheduledTaskLog $log */
        $log = SystemScheduledTaskLog::query()
            ->withoutGlobalScope(DataField::TENANT)
            ->where('id', (int)$id)
            ->first();
        if (!$log instanceof SystemScheduledTaskLog) {
            throw new \RuntimeException('定时任务执行日志创建失败');
        }

        return $log;
    }

    /**
     * @param array<string, mixed> $result
     */
    private function finishLog(SystemScheduledTaskLog $log, string $status, string $message, array $result, string $finishedAt, int $durationMs): void
    {
        $encoded = json_encode($result, JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            $encoded = json_encode(['message' => '结果摘要编码失败'], JSON_UNESCAPED_UNICODE) ?: '{}';
        }

        Db::table('system_scheduled_task_log')
            ->where('id', (int)$log->id)
            ->update([
                'status' => $status,
                'message' => $message,
                'result' => $encoded,
                'finished_at' => $finishedAt,
                'duration_ms' => $durationMs,
                'updated_at' => $finishedAt,
            ]);
    }

    /**
     * 执行锁心跳只续当前 lock_token；进程异常退出后心跳停止，锁按 timeout 失效后才允许重新抢占。
     *
     * @return array{running: bool}
     */
    private function startHeartbeat(SystemScheduledTask $task): array
    {
        $state = ['running' => true];
        $timeout = max(60, (int)$task->timeout);
        $interval = max(10, min(60, (int)floor($timeout / 3)));

        Coroutine::create(function () use ($task, &$state, $timeout, $interval): void {
            while ($state['running']) {
                Coroutine::sleep($interval);
                if (!$state['running']) {
                    break;
                }

                $lockedUntil = date('Y-m-d H:i:s', time() + $timeout);
                if (!$this->tasks->heartbeat($task, $lockedUntil)) {
                    $state['running'] = false;
                    break;
                }
            }
        });

        return $state;
    }

    /**
     * 任务结果只保存可审计摘要，避免插件任务把敏感字段或超大内容写入日志表。
     *
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function sanitizeResult(array $result): array
    {
        $sanitized = $this->sanitizeValue($result);
        $json = json_encode($sanitized, JSON_UNESCAPED_UNICODE);
        if ($json !== false && strlen($json) <= self::RESULT_MAX_BYTES) {
            return is_array($sanitized) ? $sanitized : ['value' => $sanitized];
        }

        return [
            'truncated' => true,
            'message' => '结果摘要超过长度限制，已截断',
        ];
    }

    private function sanitizeValue(mixed $value, int $depth = 0): mixed
    {
        if ($depth >= self::RESULT_MAX_DEPTH) {
            return '[DEPTH_LIMIT]';
        }
        if (is_array($value)) {
            $items = [];
            $index = 0;
            foreach ($value as $key => $item) {
                if ($index >= self::RESULT_MAX_ITEMS) {
                    $items['__truncated_items'] = count($value) - self::RESULT_MAX_ITEMS;
                    break;
                }
                $stringKey = is_string($key) ? $key : (string)$key;
                $items[$key] = $this->isSensitiveKey($stringKey)
                    ? '[FILTERED]'
                    : $this->sanitizeValue($item, $depth + 1);
                ++$index;
            }

            return $items;
        }
        if (is_string($value)) {
            return mb_strlen($value) > 2000 ? mb_substr($value, 0, 2000) . '...' : $value;
        }
        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return '[UNSUPPORTED]';
    }

    private function isSensitiveKey(string $key): bool
    {
        return preg_match('/password|passwd|pwd|token|secret|key|cookie|authorization/i', $key) === 1;
    }

    private function nextRunAt(SystemScheduledTask $task, ?string $baseTime = null): string
    {
        return ScheduleCalculator::nextRunAt(
            (string)$task->schedule_type,
            is_array($task->schedule_config) ? $task->schedule_config : [],
            $baseTime
        );
    }
}
