<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */
use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

return new class extends Migration {
    public function up(): void
    {
        $this->createTask();
        $this->createLog();
        $this->upgradeTaskOwnerColumns();
        $this->upgradeLogOwnerColumns();
    }

    public function down(): void
    {
        Schema::dropIfExists('system_scheduled_task_log');
        Schema::dropIfExists('system_scheduled_task');
    }

    private function createTask(): void
    {
        if (Schema::hasTable('system_scheduled_task')) {
            return;
        }

        Schema::create('system_scheduled_task', function (Blueprint $table): void {
            $table->addColumn('bigInteger', 'id', ['autoIncrement' => true, 'unsigned' => true])->comment('主键ID');
            $table->addColumn('bigInteger', 'tenant_id')->nullable()->default(0)->comment('租户ID');
            $table->addColumn('string', 'owner_plugin', ['length' => 60])->nullable()->default('system')->comment('归属插件');
            $table->addColumn('string', 'owner_type', ['length' => 60])->nullable()->default('system')->comment('归属类型');
            $table->addColumn('bigInteger', 'owner_id')->nullable()->default(0)->comment('归属资源ID');
            $table->addColumn('string', 'owner_name', ['length' => 120])->nullable()->default('系统任务')->comment('归属资源名称');
            $table->addColumn('string', 'code', ['length' => 120])->nullable()->default('')->comment('任务编码');
            $table->addColumn('string', 'name', ['length' => 120])->nullable()->default('')->comment('任务名称');
            $table->addColumn('string', 'group_name', ['length' => 60])->nullable()->default('system')->comment('任务分组');
            $table->addColumn('string', 'schedule_type', ['length' => 40])->nullable()->default('daily')->comment('周期类型');
            $table->addColumn('text', 'schedule_config')->nullable()->comment('周期配置JSON');
            $table->addColumn('text', 'params')->nullable()->comment('执行参数JSON');
            $table->addColumn('bigInteger', 'timeout')->nullable()->default(3600)->comment('超时时间秒');
            $table->addColumn('timestamp', 'next_run_at')->nullable()->comment('下次执行时间');
            $table->addColumn('timestamp', 'last_run_at')->nullable()->comment('最后执行时间');
            $table->addColumn('string', 'last_status', ['length' => 30])->nullable()->default('pending')->comment('最后执行状态');
            $table->addColumn('string', 'last_message', ['length' => 2000])->nullable()->default('')->comment('最后执行消息');
            $table->addColumn('bigInteger', 'running')->nullable()->default(0)->comment('是否执行中');
            $table->addColumn('timestamp', 'locked_until')->nullable()->comment('锁过期时间');
            $table->addColumn('bigInteger', 'status')->nullable()->default(1)->comment('状态(1启用,0禁用)');
            $table->addColumn('string', 'remark', ['length' => 1000])->nullable()->default('')->comment('备注');
            $table->addColumn('bigInteger', 'created_by')->nullable()->default(0)->comment('创建者');
            $table->addColumn('bigInteger', 'updated_by')->nullable()->default(0)->comment('更新者');
            $table->addColumn('timestamp', 'created_at')->nullable()->comment('创建时间');
            $table->addColumn('timestamp', 'updated_at')->nullable()->comment('更新时间');
            $table->addColumn('timestamp', 'deleted_at')->nullable()->comment('删除时间');
            $table->unique(['tenant_id', 'owner_plugin', 'owner_type', 'owner_id', 'code'], 'uni_sched_task_owner_code');
            $table->index(['tenant_id', 'status', 'next_run_at'], 'idx_sched_task_due');
            $table->index(['owner_plugin', 'owner_type', 'owner_id'], 'idx_sched_task_owner');
            $table->index(['running', 'locked_until'], 'idx_sched_task_lock');
            $table->index(['deleted_at'], 'idx_sched_task_deleted');
            $table->comment('系统定时任务表');
        });
    }

    private function createLog(): void
    {
        if (Schema::hasTable('system_scheduled_task_log')) {
            return;
        }

        Schema::create('system_scheduled_task_log', function (Blueprint $table): void {
            $table->addColumn('bigInteger', 'id', ['autoIncrement' => true, 'unsigned' => true])->comment('主键ID');
            $table->addColumn('bigInteger', 'tenant_id')->nullable()->default(0)->comment('租户ID');
            $table->addColumn('bigInteger', 'task_id')->nullable()->default(0)->comment('任务ID');
            $table->addColumn('string', 'owner_plugin', ['length' => 60])->nullable()->default('system')->comment('归属插件');
            $table->addColumn('string', 'owner_type', ['length' => 60])->nullable()->default('system')->comment('归属类型');
            $table->addColumn('bigInteger', 'owner_id')->nullable()->default(0)->comment('归属资源ID');
            $table->addColumn('string', 'owner_name', ['length' => 120])->nullable()->default('系统任务')->comment('归属资源名称');
            $table->addColumn('string', 'task_code', ['length' => 120])->nullable()->default('')->comment('任务编码');
            $table->addColumn('string', 'task_name', ['length' => 120])->nullable()->default('')->comment('任务名称');
            $table->addColumn('string', 'trigger_type', ['length' => 30])->nullable()->default('auto')->comment('触发方式');
            $table->addColumn('string', 'status', ['length' => 30])->nullable()->default('running')->comment('状态');
            $table->addColumn('string', 'message', ['length' => 2000])->nullable()->default('')->comment('结果消息');
            $table->addColumn('text', 'result')->nullable()->comment('结果摘要JSON');
            $table->addColumn('timestamp', 'started_at')->nullable()->comment('开始时间');
            $table->addColumn('timestamp', 'finished_at')->nullable()->comment('结束时间');
            $table->addColumn('bigInteger', 'duration_ms')->nullable()->default(0)->comment('耗时毫秒');
            $table->addColumn('timestamp', 'created_at')->nullable()->comment('创建时间');
            $table->addColumn('timestamp', 'updated_at')->nullable()->comment('更新时间');
            $table->index(['tenant_id', 'task_id', 'started_at'], 'idx_sched_log_task_started');
            $table->index(['owner_plugin', 'owner_type', 'owner_id', 'started_at'], 'idx_sched_log_owner_started');
            $table->index(['task_code', 'status'], 'idx_sched_log_code_status');
            $table->index(['trigger_type', 'started_at'], 'idx_sched_log_trigger_started');
            $table->comment('系统定时任务执行日志表');
        });
    }

    private function upgradeTaskOwnerColumns(): void
    {
        if (!Schema::hasTable('system_scheduled_task')) {
            return;
        }

        Schema::table('system_scheduled_task', function (Blueprint $table): void {
            $this->stringColumn('system_scheduled_task', $table, 'owner_plugin', 60, 'system', '归属插件');
            $this->stringColumn('system_scheduled_task', $table, 'owner_type', 60, 'system', '归属类型');
            $this->bigIntegerColumn('system_scheduled_task', $table, 'owner_id', 0, '归属资源ID');
            $this->stringColumn('system_scheduled_task', $table, 'owner_name', 120, '系统任务', '归属资源名称');
        });
        $this->dropTaskIndexIfExists('uni_sched_task_tenant_code', true);
        $this->ensureTaskUniqueIndex('uni_sched_task_owner_code', ['tenant_id', 'owner_plugin', 'owner_type', 'owner_id', 'code']);
        $this->ensureTaskIndex('idx_sched_task_owner', ['owner_plugin', 'owner_type', 'owner_id']);
    }

    private function upgradeLogOwnerColumns(): void
    {
        if (!Schema::hasTable('system_scheduled_task_log')) {
            return;
        }

        Schema::table('system_scheduled_task_log', function (Blueprint $table): void {
            $this->stringColumn('system_scheduled_task_log', $table, 'owner_plugin', 60, 'system', '归属插件');
            $this->stringColumn('system_scheduled_task_log', $table, 'owner_type', 60, 'system', '归属类型');
            $this->bigIntegerColumn('system_scheduled_task_log', $table, 'owner_id', 0, '归属资源ID');
            $this->stringColumn('system_scheduled_task_log', $table, 'owner_name', 120, '系统任务', '归属资源名称');
        });
        $this->ensureLogIndex('idx_sched_log_owner_started', ['owner_plugin', 'owner_type', 'owner_id', 'started_at']);
    }

    private function stringColumn(string $tableName, Blueprint $table, string $name, int $length, string $default, string $comment): void
    {
        if (Schema::hasColumn($tableName, $name)) {
            return;
        }

        $table->addColumn('string', $name, ['length' => $length])->nullable()->default($default)->comment($comment);
    }

    private function bigIntegerColumn(string $tableName, Blueprint $table, string $name, int $default, string $comment): void
    {
        if (Schema::hasColumn($tableName, $name)) {
            return;
        }

        $table->addColumn('bigInteger', $name)->nullable()->default($default)->comment($comment);
    }

    /**
     * @param array<int, string> $columns
     */
    private function ensureTaskUniqueIndex(string $name, array $columns): void
    {
        if (Schema::hasIndex('system_scheduled_task', $name, 'unique') || Schema::hasIndex('system_scheduled_task', $columns, 'unique')) {
            return;
        }

        Schema::table('system_scheduled_task', static function (Blueprint $table) use ($columns, $name): void {
            $table->unique($columns, $name);
        });
    }

    /**
     * @param array<int, string> $columns
     */
    private function ensureTaskIndex(string $name, array $columns): void
    {
        if (Schema::hasIndex('system_scheduled_task', $name) || Schema::hasIndex('system_scheduled_task', $columns)) {
            return;
        }

        Schema::table('system_scheduled_task', static function (Blueprint $table) use ($columns, $name): void {
            $table->index($columns, $name);
        });
    }

    /**
     * @param array<int, string> $columns
     */
    private function ensureLogIndex(string $name, array $columns): void
    {
        if (Schema::hasIndex('system_scheduled_task_log', $name) || Schema::hasIndex('system_scheduled_task_log', $columns)) {
            return;
        }

        Schema::table('system_scheduled_task_log', static function (Blueprint $table) use ($columns, $name): void {
            $table->index($columns, $name);
        });
    }

    private function dropTaskIndexIfExists(string $name, bool $unique = false): void
    {
        if (!Schema::hasIndex('system_scheduled_task', $name, $unique ? 'unique' : null)) {
            return;
        }

        Schema::table('system_scheduled_task', static function (Blueprint $table) use ($name, $unique): void {
            $unique ? $table->dropUnique($name) : $table->dropIndex($name);
        });
    }
};
