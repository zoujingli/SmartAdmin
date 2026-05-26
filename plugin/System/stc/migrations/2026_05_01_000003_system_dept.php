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
        if (Schema::hasTable('system_dept')) {
            return;
        }

        Schema::create('system_dept', function (Blueprint $table) {
            $table->addColumn('bigInteger', 'id', ['autoIncrement' => true, 'unsigned' => true])->comment('主键ID');
            $table->addColumn('bigInteger', 'pid', [])->nullable()->default(0)->comment('上级部门ID');
            $table->addColumn('string', 'code', ['length' => 50])->nullable()->default('')->comment('部门编码');
            $table->addColumn('string', 'name', ['length' => 30])->nullable()->default('')->comment('部门名称');
            $table->addColumn('string', 'phone', ['length' => 11])->nullable()->default('')->comment('联系电话');
            $table->addColumn('string', 'email', ['length' => 50])->nullable()->default('')->comment('部门邮箱');
            $table->addColumn('string', 'level', ['length' => 500])->nullable()->default('')->comment('层级路径');
            $table->addColumn('string', 'leader', ['length' => 20])->nullable()->default('')->comment('负责人');
            $table->addColumn('bigInteger', 'sort', [])->nullable()->default(0)->comment('排序权重');
            $table->addColumn('bigInteger', 'status', [])->nullable()->default(1)->comment('状态(1启用,0禁用)');
            $table->addColumn('string', 'remark', ['length' => 255])->nullable()->default('')->comment('备注');
            $table->addColumn('bigInteger', 'created_by', [])->nullable()->default(0)->comment('创建者');
            $table->addColumn('bigInteger', 'updated_by', [])->nullable()->default(0)->comment('更新者');
            $table->addColumn('timestamp', 'created_at', [])->nullable()->comment('创建时间');
            $table->addColumn('timestamp', 'updated_at', [])->nullable()->comment('更新时间');
            $table->addColumn('timestamp', 'deleted_at', [])->nullable()->comment('删除时间');
            $table->addColumn('bigInteger', 'tenant_id', [])->nullable()->default(0)->comment('租户ID');
            $table->index(['deleted_at'], 'idx_sd_dbda_deleted_at');
            $table->index(['pid'], 'idx_sd_dbda_pid');
            $table->index(['sort'], 'idx_sd_dbda_sort');
            $table->index(['status'], 'idx_sd_dbda_status');
            $table->index(['tenant_id'], 'idx_sd_dbda_tenant_id');
            $table->unique(['tenant_id', 'code'], 'uni_sd_dbda_tenant_id_code');
            $table->comment('系统部门表');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_dept');
    }
};
