<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Tests\Unit\System;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use System\Support\Scheduler\ScheduleCalculator;

/**
 * @internal
 */
#[CoversClass(ScheduleCalculator::class)]
final class SystemSchedulerContractTest extends TestCase
{
    public function testScheduleCalculatorUsesFormBasedCycles(): void
    {
        self::assertSame('2026-06-11 10:15:00', ScheduleCalculator::nextRunAt('every_minutes', ['interval' => 5], '2026-06-11 10:12:00'));
        self::assertSame('2026-06-11 11:20:00', ScheduleCalculator::nextRunAt('hourly', ['minute' => 20], '2026-06-11 10:20:00'));
        self::assertSame('2026-06-12 02:30:00', ScheduleCalculator::nextRunAt('daily', ['hour' => 2, 'minute' => 30], '2026-06-11 03:00:00'));
        self::assertSame('2026-06-15 02:30:00', ScheduleCalculator::nextRunAt('weekly', ['weekday' => 1, 'hour' => 2, 'minute' => 30], '2026-06-11 03:00:00'));
        self::assertSame('2026-07-31 02:30:00', ScheduleCalculator::nextRunAt('monthly', ['day' => 31, 'hour' => 2, 'minute' => 30], '2026-06-30 03:00:00'));
    }

    public function testSystemSchedulerBackendContractsStayClosed(): void
    {
        $root = dirname(__DIR__, 3);
        $provider = (string)file_get_contents($root . '/plugin/System/src/Provider.php');
        $process = (string)file_get_contents($root . '/plugin/System/src/Process/SystemSchedulerProcess.php');
        $listener = (string)file_get_contents($root . '/plugin/System/src/Listener/SystemProcessRegisterListener.php');
        $taskController = (string)file_get_contents($root . '/plugin/System/src/Controller/ScheduledTaskController.php');
        $logController = (string)file_get_contents($root . '/plugin/System/src/Controller/ScheduledTaskLogController.php');
        $registry = (string)file_get_contents($root . '/plugin/System/src/Support/Scheduler/ScheduledTaskRegistry.php');
        $executor = (string)file_get_contents($root . '/plugin/System/src/Service/ScheduledTaskExecutor.php');
        $composer = (string)file_get_contents($root . '/composer.json');
        $lock = (string)file_get_contents($root . '/composer.lock');

        self::assertStringContainsString('SystemProcessRegisterListener::class', $provider);
        self::assertStringContainsString('ScheduledTaskExecutorInterface::class => ScheduledTaskExecutor::class', $provider);
        self::assertStringContainsString("#[SystemProcess(name: 'system.scheduler', default: true)]", $process);
        self::assertStringContainsString('return $server instanceof Server || $server instanceof CoServer || $server instanceof CoHttpServer;', $process);
        self::assertStringNotContainsString('SYSTEM_SCHEDULER_ENABLED', $process . $listener . $provider);
        self::assertStringContainsString('BeforeMainServerStart::class', $listener);
        self::assertStringContainsString('$process->bind($event->server)', $listener);
        self::assertStringContainsString("Controller(prefix: 'system/scheduler/task')", $taskController);
        self::assertStringContainsString("Controller(prefix: 'system/scheduler/log')", $logController);
        foreach ([
            'system.scheduler.task.index',
            'system.scheduler.task.create',
            'system.scheduler.task.update',
            'system.scheduler.task.delete',
            'system.scheduler.task.status',
            'system.scheduler.task.run',
            'system.scheduler.log.index',
        ] as $code) {
            self::assertStringContainsString($code, $taskController . $logController);
        }
        self::assertStringContainsString('AnnotationCollector::getClassesByAnnotation(ScheduledTask::class)', $registry);
        self::assertStringContainsString('ScheduledTaskHandlerInterface::class', $registry);
        self::assertStringContainsString('TenantContext::withTenant((int)$task->tenant_id', $executor);
        self::assertStringContainsString('ScheduledTaskExecutorInterface', $executor);
        self::assertStringNotContainsString('hyperf/async-queue', $composer . $lock);
        self::assertStringNotContainsString('hyperf/crontab', $composer . $lock);
    }

    public function testSystemSchedulerSchemaMenuAndFrontendAreDiscoverable(): void
    {
        $root = dirname(__DIR__, 3);
        $migration = (string)file_get_contents($root . '/plugin/System/stc/migrations/2026_06_11_000001_system_scheduler.php');
        $menuSeed = (string)file_get_contents($root . '/plugin/System/src/Support/SystemMenuSeed.php');
        $manifest = json_decode((string)file_get_contents($root . '/plugin/System/plugin.json'), true, flags: JSON_THROW_ON_ERROR);
        $api = (string)file_get_contents($root . '/web/apps/web-antd/src/api/system/scheduler.ts');
        $view = (string)file_get_contents($root . '/plugin/System/stc/view/scheduler/task/index.vue');

        self::assertStringContainsString('system_scheduled_task', $migration);
        self::assertStringContainsString('system_scheduled_task_log', $migration);
        self::assertStringContainsString('owner_plugin', $migration);
        self::assertStringContainsString('owner_type', $migration);
        self::assertStringContainsString('owner_id', $migration);
        self::assertStringContainsString('uni_sched_task_owner_code', $migration);
        self::assertStringContainsString('idx_sched_task_lock', $migration);
        self::assertStringContainsString('@plugin/System/views/scheduler/task/index.vue', $menuSeed);
        $opsMenus = $manifest['apps'][0]['menus'] ?? [];
        $taskMenu = array_values(array_filter($opsMenus, static fn (array $menu): bool => ($menu['code'] ?? '') === 'system.scheduler.task.index'))[0] ?? null;
        self::assertIsArray($taskMenu);
        self::assertSame('scheduler/task/index.vue', $taskMenu['view'] ?? null);
        self::assertContains('system.scheduler.task.run', array_column($taskMenu['permissions'] ?? [], 'code'));
        self::assertContains('system.scheduler.log.index', array_column($taskMenu['permissions'] ?? [], 'code'));
        self::assertStringContainsString('system/scheduler/task/index', $api);
        self::assertStringContainsString('system/scheduler/log/index', $api);
        self::assertStringContainsString('owner_plugin: string', $api);
        self::assertStringContainsString('owner_name: string', $api);
        self::assertStringContainsString('CrudTableActions', $view);
        self::assertStringContainsString('confirmTitle', $view);
        self::assertStringContainsString('<Page title="定时任务">', $view);
        self::assertStringContainsString('CrudTableHeader title="定时任务"', $view);
        self::assertStringContainsString('SearchField label="搜索内容"', $view);
        self::assertStringContainsString('SearchField label="启用状态"', $view);
        self::assertStringContainsString('SearchField label="最近结果"', $view);
        self::assertStringContainsString('formState.schedule_type === \'every_minutes\'', $view);
        self::assertStringContainsString('isSystemOwned(record)', $view);
        self::assertStringContainsString('ownerText(record', $view);
        self::assertStringContainsString('durationText(record.duration_ms)', $view);
        self::assertStringContainsString('CollapsePanel key="advanced" header="高级设置"', $view);
        self::assertStringContainsString('业务插件任务只能查看，规则请到对应业务页面编辑', $view);
        self::assertStringContainsString("{ title: '任务名称', dataIndex: 'name', key: 'name'", $view);
        self::assertStringContainsString("{ title: '所属模块', key: 'owner'", $view);
        self::assertStringContainsString("{ title: '下次执行', dataIndex: 'next_run_at'", $view);
        self::assertStringContainsString("{ title: '最近执行', dataIndex: 'last_run_at'", $view);
        self::assertStringContainsString("{ title: '任务名称', dataIndex: 'task_name'", $view);
        self::assertStringContainsString("{ title: '触发方式', dataIndex: 'trigger_type'", $view);
        self::assertStringContainsString("{ title: '执行结果', dataIndex: 'status'", $view);
        self::assertStringContainsString("{ title: '耗时', dataIndex: 'duration_ms'", $view);
        self::assertStringNotContainsString('CrudStatCards', $view);
        self::assertStringNotContainsString('刷新运行态', $view);
        self::assertStringNotContainsString('默认启动', $view);
        self::assertStringNotContainsString('默认进程', $view);
        self::assertStringNotContainsString('服务器时间', $view);
        self::assertStringNotContainsString('cron', strtolower($view));
    }

    public function testSchedulerSharedKernelSupportsPluginOwnedTasks(): void
    {
        $root = dirname(__DIR__, 3);
        $annotation = (string)file_get_contents($root . '/plugin/System/src/Annotation/ScheduledTask.php');
        $definition = (string)file_get_contents($root . '/plugin/System/src/Support/Scheduler/ScheduledTaskDefinition.php');
        $context = (string)file_get_contents($root . '/plugin/System/src/Support/Scheduler/ScheduledTaskContext.php');
        $mapper = (string)file_get_contents($root . '/plugin/System/src/Mapper/ScheduledTaskMapper.php');
        $pluginService = (string)file_get_contents($root . '/plugin/System/src/Service/PluginScheduledTaskService.php');
        $systemService = (string)file_get_contents($root . '/plugin/System/src/Service/ScheduledTaskService.php');
        $executor = (string)file_get_contents($root . '/plugin/System/src/Service/ScheduledTaskExecutor.php');

        self::assertStringContainsString('public string $ownerPlugin = \'system\'', $annotation);
        self::assertStringContainsString('ownerPlugin: $annotation->ownerPlugin', $definition);
        self::assertStringContainsString('public string $ownerPlugin', $context);
        self::assertStringContainsString('public string $ownerType', $context);
        self::assertStringContainsString('public int $ownerId', $context);
        self::assertStringContainsString('final class PluginScheduledTaskService', $pluginService);
        self::assertStringContainsString('$definition->ownerPlugin !== $ownerPlugin', $pluginService);
        self::assertStringContainsString('?int $tenantId = null', $mapper);
        self::assertStringContainsString("withoutGlobalScope(DataField::TENANT)->where('tenant_id', \$tenantId)", $mapper);
        self::assertStringContainsString('updateOwned(SystemScheduledTask $task', $mapper);
        self::assertStringContainsString('deleteOwned(SystemScheduledTask $task', $mapper);
        self::assertStringContainsString('TenantContext::requireTenantId()', $pluginService);
        self::assertStringContainsString('$this->tasks->updateOwned($exists, $payload)', $pluginService);
        self::assertStringContainsString('return $this->ownedTask(', $pluginService);
        self::assertStringContainsString('$this->tasks->deleteOwned($task)', $pluginService);
        self::assertStringContainsString('findByOwner', $pluginService);
        self::assertStringContainsString('$exists->restore()', $pluginService);
        self::assertStringContainsString('业务插件任务请在对应子系统中管理', $systemService);
        self::assertStringContainsString('$trashed->restore()', $systemService);
        self::assertStringContainsString('ownerPlugin: (string)($task->owner_plugin ?: \'system\')', $executor);
        self::assertStringContainsString("'owner_plugin' => (string)(\$task->owner_plugin ?: 'system')", $executor);
    }

    public function testBuiltinScheduledTasksAreWhitelistHandlers(): void
    {
        $root = dirname(__DIR__, 3);
        $logs = (string)file_get_contents($root . '/plugin/System/src/Support/Scheduler/Task/SystemLogsClearTask.php');
        $cache = (string)file_get_contents($root . '/plugin/System/src/Support/Scheduler/Task/SystemCacheClearTask.php');
        $database = (string)file_get_contents($root . '/plugin/System/src/Support/Scheduler/Task/SystemDatabaseOptimizeTask.php');

        foreach ([
            'system.logs.clear' => $logs,
            'system.cache.clear' => $cache,
            'system.database.optimize' => $database,
        ] as $code => $source) {
            self::assertStringContainsString("#[ScheduledTask(", $source);
            self::assertStringContainsString($code, $source);
            self::assertStringContainsString('ScheduledTaskHandlerInterface', $source);
        }
        self::assertStringContainsString("where('tenant_id', \$context->tenantId)", $logs);
        self::assertStringContainsString('AuthProcessor::clearCache()', $cache);
        self::assertStringContainsString('OPTIMIZE TABLE', $database);
        self::assertStringContainsString('VACUUM', $database);
        self::assertStringNotContainsString('shell_exec', $database . $logs . $cache);
        self::assertStringNotContainsString('exec(', $database . $logs . $cache);
    }
}
