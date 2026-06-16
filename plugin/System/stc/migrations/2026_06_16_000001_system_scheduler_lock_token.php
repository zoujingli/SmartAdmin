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
        if (!Schema::hasTable('system_scheduled_task')) {
            return;
        }

        Schema::table('system_scheduled_task', function (Blueprint $table): void {
            if (!Schema::hasColumn('system_scheduled_task', 'lock_token')) {
                $table->addColumn('string', 'lock_token', ['length' => 64])->nullable()->default('')->comment('执行锁令牌');
            }
        });

        if (!Schema::hasIndex('system_scheduled_task', 'idx_sched_task_lock_token') && !Schema::hasIndex('system_scheduled_task', ['lock_token'])) {
            Schema::table('system_scheduled_task', static function (Blueprint $table): void {
                $table->index(['lock_token'], 'idx_sched_task_lock_token');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('system_scheduled_task') || !Schema::hasColumn('system_scheduled_task', 'lock_token')) {
            return;
        }

        if (Schema::hasIndex('system_scheduled_task', 'idx_sched_task_lock_token')) {
            Schema::table('system_scheduled_task', static function (Blueprint $table): void {
                $table->dropIndex('idx_sched_task_lock_token');
            });
        }

        Schema::table('system_scheduled_task', static function (Blueprint $table): void {
            $table->dropColumn('lock_token');
        });
    }
};
