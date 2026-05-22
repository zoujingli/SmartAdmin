<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('system_post')) {
            return;
        }

        Schema::create('system_post', function (Blueprint $table) {
            $table->addColumn('bigInteger', 'id', ['autoIncrement' => true, 'unsigned' => true])->comment('主键ID');
            $table->addColumn('string', 'code', ['length' => 100])->nullable()->default('')->comment('岗位编码');
            $table->addColumn('string', 'name', ['length' => 100])->nullable()->default('')->comment('岗位名称');
            $table->addColumn('bigInteger', 'sort', [])->nullable()->default(0)->comment('排序权重');
            $table->addColumn('bigInteger', 'status', [])->nullable()->default(1)->comment('状态(1启用,0禁用)');
            $table->addColumn('string', 'remark', ['length' => 255])->nullable()->default('')->comment('备注');
            $table->addColumn('bigInteger', 'created_by', [])->nullable()->default(0)->comment('创建者');
            $table->addColumn('bigInteger', 'updated_by', [])->nullable()->default(0)->comment('更新者');
            $table->addColumn('timestamp', 'created_at', [])->nullable()->comment('创建时间');
            $table->addColumn('timestamp', 'updated_at', [])->nullable()->comment('更新时间');
            $table->addColumn('timestamp', 'deleted_at', [])->nullable()->comment('删除时间');
            $table->addColumn('bigInteger', 'tenant_id', [])->nullable()->default(0)->comment('租户ID');
            $table->index(['deleted_at'], 'idx_sp_538f_deleted_at');
            $table->index(['sort'], 'idx_sp_538f_sort');
            $table->index(['status'], 'idx_sp_538f_status');
            $table->index(['tenant_id'], 'idx_sp_538f_tenant_id');
            $table->comment('系统岗位表');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_post');
    }
};
