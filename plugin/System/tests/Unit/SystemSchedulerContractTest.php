<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace System\Tests\Unit;

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
        self::assertSame('2026-06-11 10:12:31', ScheduleCalculator::nextRunAt('every_seconds', ['interval' => 1], '2026-06-11 10:12:30'));
        self::assertSame('2026-06-11 10:12:40', ScheduleCalculator::nextRunAt('every_seconds', ['interval' => 10], '2026-06-11 10:12:30'));
        self::assertSame(['interval' => 1], ScheduleCalculator::normalize('every_seconds', ['interval' => 0]));
        self::assertSame(['interval' => 86400], ScheduleCalculator::normalize('every_seconds', ['interval' => 99999]));
        self::assertSame('每 10 秒执行一次', ScheduleCalculator::describe('every_seconds', ['interval' => 10]));
        self::assertContains(['label' => '每 N 秒', 'value' => 'every_seconds'], ScheduleCalculator::types());
        self::assertSame('2026-06-11 10:15:00', ScheduleCalculator::nextRunAt('every_minutes', ['interval' => 5], '2026-06-11 10:12:00'));
        self::assertSame('2026-06-11 12:00:00', ScheduleCalculator::nextRunAt('every_hours', ['interval' => 2], '2026-06-11 10:12:00'));
        self::assertSame(['interval' => 1], ScheduleCalculator::normalize('every_hours', ['interval' => 0]));
        self::assertSame(['interval' => 8760], ScheduleCalculator::normalize('every_hours', ['interval' => 99999]));
        self::assertSame('每 2 小时执行一次', ScheduleCalculator::describe('every_hours', ['interval' => 2]));
        self::assertContains(['label' => '每 N 小时', 'value' => 'every_hours'], ScheduleCalculator::types());
        self::assertSame('2026-06-11 11:20:00', ScheduleCalculator::nextRunAt('hourly', ['minute' => 20], '2026-06-11 10:20:00'));
        self::assertSame('2026-06-12 02:30:00', ScheduleCalculator::nextRunAt('daily', ['hour' => 2, 'minute' => 30], '2026-06-11 03:00:00'));
        self::assertSame('2026-06-15 02:30:00', ScheduleCalculator::nextRunAt('weekly', ['weekday' => 1, 'hour' => 2, 'minute' => 30], '2026-06-11 03:00:00'));
        self::assertSame('2026-07-31 02:30:00', ScheduleCalculator::nextRunAt('monthly', ['day' => 31, 'hour' => 2, 'minute' => 30], '2026-06-30 03:00:00'));
    }

    public function testSystemSchedulerBackendContractsStayClosed(): void
    {
        $root = dirname(__DIR__, 4);
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
        self::assertStringContainsString("GetMapping(path: 'owner-types')", $taskController);
        self::assertStringContainsString("GetMapping(path: 'owner-options')", $taskController);
        self::assertStringContainsString('TenantContext::withTenant((int)$task->tenant_id', $executor);
        self::assertStringContainsString('ScheduledTaskExecutorInterface', $executor);
        self::assertStringContainsString('private const TICK_INTERVAL_SECONDS = 1;', $process);
        self::assertStringContainsString('Coroutine::sleep(self::TICK_INTERVAL_SECONDS)', $process);
        self::assertStringContainsString('Coroutine::create(function () use ($claimed): void', $process);
        self::assertStringNotContainsString('hyperf/async-queue', $composer . $lock);
        self::assertStringNotContainsString('hyperf/crontab', $composer . $lock);
    }

    public function testSystemSchedulerSchemaMenuAndFrontendAreDiscoverable(): void
    {
        $root = dirname(__DIR__, 4);
        $migration = (string)file_get_contents($root . '/plugin/System/stc/migrations/2026_06_11_000001_system_scheduler.php');
        $lockTokenMigration = (string)file_get_contents($root . '/plugin/System/stc/migrations/2026_06_16_000001_system_scheduler_lock_token.php');
        $menuSeed = (string)file_get_contents($root . '/plugin/System/src/Support/SystemMenuSeed.php');
        $manifest = json_decode((string)file_get_contents($root . '/plugin/System/plugin.json'), true, flags: JSON_THROW_ON_ERROR);
        $api = (string)file_get_contents($root . '/plugin/System/stc/view/api/scheduler.ts');
        $view = (string)file_get_contents($root . '/plugin/System/stc/view/scheduler/task/index.vue');
        $systemSetup = (string)file_get_contents($root . '/plugin/System/stc/view/setup.ts');
        $vite = (string)file_get_contents($root . '/web/apps/web-antd/vite.config.mts');
        $bootstrap = (string)file_get_contents($root . '/web/apps/web-antd/src/bootstrap.ts');

        self::assertStringContainsString('system_scheduled_task', $migration);
        self::assertStringContainsString('system_scheduled_task_log', $migration);
        self::assertStringContainsString('owner_plugin', $migration);
        self::assertStringContainsString('owner_type', $migration);
        self::assertStringContainsString('owner_id', $migration);
        self::assertStringContainsString('lock_token', $migration);
        self::assertStringContainsString('idx_sched_task_lock_token', $migration);
        self::assertStringContainsString('lock_token', $lockTokenMigration);
        self::assertStringContainsString('Schema::hasColumn', $lockTokenMigration);
        self::assertStringContainsString('idx_sched_task_lock_token', $lockTokenMigration);
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
        self::assertStringContainsString('system/scheduler/task/owner-types', $api);
        self::assertStringContainsString('system/scheduler/task/owner-options', $api);
        self::assertStringContainsString('owner_plugin: string', $api);
        self::assertStringContainsString('owner_name: string', $api);
        self::assertStringContainsString('export interface OwnerTypeOption', $api);
        self::assertStringContainsString('export interface OwnerOption', $api);
        self::assertStringContainsString('lock_token?: string', $api);
        self::assertStringContainsString("const pluginSetupsModuleId = 'virtual:xadmin-plugin-setups'", $vite);
        self::assertStringContainsString('function getPluginSetupFiles(): string[]', $vite);
        self::assertStringContainsString("for (const setupFile of ['setup.ts', 'setup.mts'])", $vite);
        self::assertStringContainsString('export async function setupPlugins(appContext = {})', $vite);
        self::assertStringContainsString("import { setupPlugins } from 'virtual:xadmin-plugin-setups'", $bootstrap);
        self::assertStringContainsString('await setupPlugins({ app, namespace });', $bootstrap);
        self::assertStringContainsString('configureTaskProgressProvider', $systemSetup);
        self::assertStringContainsString('/system/task/status', $systemSetup);
        self::assertStringContainsString('CrudTableActions', $view);
        self::assertStringContainsString('confirmTitle', $view);
        self::assertStringContainsString('<Page title="定时任务">', $view);
        self::assertStringContainsString('CrudTableHeader title="定时任务"', $view);
        self::assertStringContainsString('SearchField label="搜索内容"', $view);
        self::assertStringContainsString('SearchField label="启用状态"', $view);
        self::assertStringContainsString('SearchField label="最近结果"', $view);
        self::assertStringContainsString('formState.schedule_type === \'every_seconds\'', $view);
        self::assertStringContainsString('FormItem label="间隔秒"', $view);
        self::assertStringContainsString(':max="86400"', $view);
        self::assertStringContainsString("if (type === 'every_seconds') return { interval: 10 }", $view);
        self::assertStringContainsString("record.schedule_type === 'every_seconds'", $view);
        self::assertStringContainsString('formState.schedule_type === \'every_minutes\'', $view);
        self::assertStringContainsString('formState.schedule_type === \'every_hours\'', $view);
        self::assertStringContainsString('FormItem label="间隔小时"', $view);
        self::assertStringContainsString(':max="8760"', $view);
        self::assertStringContainsString("if (type === 'every_hours') return { interval: 1 }", $view);
        self::assertStringContainsString("record.schedule_type === 'every_hours'", $view);
        self::assertStringContainsString('isSystemOwned(record)', $view);
        self::assertStringContainsString('ownerText(record', $view);
        self::assertStringContainsString('selectedOwnerPlugin', $view);
        self::assertStringContainsString('loadOwnerOptions', $view);
        self::assertStringContainsString('getOwnerTypes', $view);
        self::assertStringContainsString('getOwnerOptions', $view);
        self::assertStringContainsString('option-label-prop="label"', $view);
        self::assertStringContainsString(':label="taskOptionLabel(item)"', $view);
        self::assertStringContainsString(':label="ownerOptionLabel(item)"', $view);
        self::assertStringContainsString('function taskOptionLabel(item: SchedulerApi.TaskDefinition)', $view);
        self::assertStringContainsString('function ownerOptionLabel(item: SchedulerApi.OwnerOption)', $view);
        self::assertStringContainsString('canManageTask(record)', $view);
        self::assertStringContainsString('manageableOwnerKeys', $view);
        self::assertStringContainsString('durationText(record.duration_ms)', $view);
        self::assertStringContainsString('CollapsePanel key="advanced" header="高级设置"', $view);
        self::assertStringContainsString('统一运维当前租户下全部注册任务', $view);
        self::assertStringNotContainsString('业务插件任务只能查看，规则请到对应业务页面编辑', $view);
        self::assertStringNotContainsString("filter((item) => item.owner_plugin === 'system')", $view);
        self::assertStringContainsString('disabled: !enabled || running', $view);
        self::assertStringContainsString('disabled: running', $view);
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
        $root = dirname(__DIR__, 4);
        $annotation = (string)file_get_contents($root . '/plugin/System/src/Annotation/ScheduledTask.php');
        $definition = (string)file_get_contents($root . '/plugin/System/src/Support/Scheduler/ScheduledTaskDefinition.php');
        $context = (string)file_get_contents($root . '/plugin/System/src/Support/Scheduler/ScheduledTaskContext.php');
        $mapper = (string)file_get_contents($root . '/plugin/System/src/Mapper/ScheduledTaskMapper.php');
        $pluginService = (string)file_get_contents($root . '/plugin/System/src/Service/PluginScheduledTaskService.php');
        $systemService = (string)file_get_contents($root . '/plugin/System/src/Service/ScheduledTaskService.php');
        $executor = (string)file_get_contents($root . '/plugin/System/src/Service/ScheduledTaskExecutor.php');
        $ownerAnnotation = (string)file_get_contents($root . '/plugin/System/src/Annotation/ScheduledTaskOwner.php');
        $ownerRegistry = (string)file_get_contents($root . '/plugin/System/src/Support/Scheduler/ScheduledTaskOwnerRegistry.php');
        $systemOwnerResolver = (string)file_get_contents($root . '/plugin/System/src/Support/Scheduler/SystemScheduledTaskOwnerResolver.php');

        self::assertStringContainsString('public string $ownerPlugin = \'system\'', $annotation);
        self::assertStringContainsString('ownerPlugin: $annotation->ownerPlugin', $definition);
        self::assertStringContainsString('public string $ownerPlugin', $context);
        self::assertStringContainsString('public string $ownerType', $context);
        self::assertStringContainsString('public int $ownerId', $context);
        self::assertStringContainsString('final class ScheduledTaskOwner', $ownerAnnotation);
        self::assertStringContainsString('ScheduledTaskOwnerResolverInterface', $ownerRegistry);
        self::assertStringContainsString('AnnotationCollector::getClassesByAnnotation(ScheduledTaskOwner::class)', $ownerRegistry);
        self::assertStringContainsString('#[ScheduledTaskOwner(', $systemOwnerResolver);
        self::assertStringContainsString("ownerPlugin: 'system'", $systemOwnerResolver);
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
        self::assertStringContainsString('ScheduledTaskOwnerRegistry $owners', $systemService);
        self::assertStringContainsString('$this->owners->resolve($ownerPlugin, $ownerType, $ownerId, $data)', $systemService);
        self::assertStringContainsString('schedule_type.in:every_seconds,every_minutes,every_hours,hourly,daily,weekly,monthly', $systemService);
        self::assertStringContainsString('ensureOwnerManageable', $systemService);
        self::assertStringContainsString('任务归属解析器不存在，只能查看该业务任务', $systemService);
        self::assertStringContainsString('任务类型和归属不能直接修改，请删除后重新创建', $systemService);
        self::assertStringContainsString('$this->mapper->deleteOwned($task)', $systemService);
        self::assertStringNotContainsString('业务插件任务请在对应子系统中管理', $systemService);
        self::assertStringContainsString('$trashed->restore()', $systemService);
        self::assertStringContainsString('ownerPlugin: (string)($task->owner_plugin ?: \'system\')', $executor);
        self::assertStringContainsString("'owner_plugin' => (string)(\$task->owner_plugin ?: 'system')", $executor);
    }

    public function testSchedulerLockingFailureAndSanitizingContracts(): void
    {
        $root = dirname(__DIR__, 4);
        $process = (string)file_get_contents($root . '/plugin/System/src/Process/SystemSchedulerProcess.php');
        $mapper = (string)file_get_contents($root . '/plugin/System/src/Mapper/ScheduledTaskMapper.php');
        $executor = (string)file_get_contents($root . '/plugin/System/src/Service/ScheduledTaskExecutor.php');
        $systemService = (string)file_get_contents($root . '/plugin/System/src/Service/ScheduledTaskService.php');
        $pluginService = (string)file_get_contents($root . '/plugin/System/src/Service/PluginScheduledTaskService.php');

        self::assertStringContainsString("->where('tenant_id', '>', 0)", $mapper);
        self::assertStringContainsString('markInvalidDueTasks', $mapper . $process);
        self::assertStringContainsString('任务租户上下文无效，已跳过执行', $mapper);
        self::assertStringContainsString('makeLockToken', $mapper);
        self::assertStringContainsString("'lock_token' => \$token", $mapper);
        self::assertStringContainsString("->where('lock_token', (string)\$task->lock_token)", $mapper);
        self::assertStringContainsString('public function heartbeat(SystemScheduledTask $task', $mapper);
        self::assertStringContainsString('try {', $process);
        self::assertStringContainsString('System scheduler task [%s] failed', $process);

        self::assertStringContainsString('startHeartbeat', $executor);
        self::assertStringContainsString('finally {', $executor);
        self::assertStringContainsString("\$heartbeat['running'] = false", $executor);
        self::assertStringContainsString('日志创建失败', $executor);
        self::assertStringContainsString('日志更新失败', $executor);
        self::assertStringContainsString('sanitizeResult', $executor);
        self::assertStringContainsString('isSensitiveKey', $executor);
        self::assertStringContainsString('password|passwd|pwd|token|secret|key|cookie|authorization', $executor);
        self::assertStringContainsString('RESULT_MAX_BYTES', $executor);

        foreach ([$systemService, $pluginService] as $source) {
            self::assertStringContainsString('任务已禁用，不能立即执行', $source);
            self::assertStringContainsString('ensureNotRunning', $source);
        }
    }

    public function testBuiltinScheduledTasksAreWhitelistHandlers(): void
    {
        $root = dirname(__DIR__, 4);
        $logs = (string)file_get_contents($root . '/plugin/System/src/Support/Scheduler/Task/SystemLogsClearTask.php');
        $cache = (string)file_get_contents($root . '/plugin/System/src/Support/Scheduler/Task/SystemCacheClearTask.php');
        $database = (string)file_get_contents($root . '/plugin/System/src/Support/Scheduler/Task/SystemDatabaseOptimizeTask.php');

        foreach ([
            'system.logs.clear' => $logs,
            'system.cache.clear' => $cache,
            'system.database.optimize' => $database,
        ] as $code => $source) {
            self::assertStringContainsString('#[ScheduledTask(', $source);
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
