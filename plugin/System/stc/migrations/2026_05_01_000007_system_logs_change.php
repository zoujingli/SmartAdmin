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
        if (Schema::hasTable('system_logs_change')) {
            return;
        }

        Schema::create('system_logs_change', function (Blueprint $table) {
            $table->addColumn('bigInteger', 'id', ['autoIncrement' => true, 'unsigned' => true])->comment('主键ID');
            $table->addColumn('bigInteger', 'tenant_id', [])->nullable()->default(0)->comment('租户ID');
            $table->addColumn('bigInteger', 'action_id', [])->nullable()->default(0)->comment('操作日志ID');
            $table->addColumn('string', 'username', ['length' => 20])->nullable()->default('')->comment('操作用户名');
            $table->addColumn('string', 'model', ['length' => 100])->nullable()->default('')->comment('模型短名');
            $table->addColumn('string', 'table_name', ['length' => 100])->nullable()->default('')->comment('业务表名');
            $table->addColumn('string', 'model_name', ['length' => 100])->nullable()->default('')->comment('业务对象名称');
            $table->addColumn('string', 'record_id', ['length' => 100])->nullable()->default('')->comment('业务记录ID');
            $table->addColumn('string', 'record_label', ['length' => 200])->nullable()->default('')->comment('业务记录展示名');
            $table->addColumn('string', 'event', ['length' => 50])->nullable()->default('')->comment('变更动作(created,updated,deleted,force_deleted,restored)');
            $table->addColumn('text', 'change_values', [])->nullable()->comment('字段变化明细(JSON)');
            $table->addColumn('text', 'change_remark', [])->nullable()->comment('可读变更描述');
            $table->addColumn('bigInteger', 'created_by', [])->nullable()->default(0)->comment('创建者');
            $table->addColumn('bigInteger', 'updated_by', [])->nullable()->default(0)->comment('更新者');
            $table->addColumn('timestamp', 'created_at', [])->nullable()->comment('创建时间');
            $table->addColumn('timestamp', 'updated_at', [])->nullable()->comment('更新时间');
            $table->addColumn('timestamp', 'deleted_at', [])->nullable()->comment('删除时间');
            $table->index(['action_id'], 'idx_slc_f1ad_action_id');
            $table->index(['deleted_at'], 'idx_slc_f1ad_deleted_at');
            $table->index(['model', 'record_id'], 'idx_slc_f1ad_model_fc1531a0');
            $table->index(['tenant_id'], 'idx_slc_f1ad_tenant_id');
            $table->comment('系统变更日志表');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_logs_change');
    }
};
