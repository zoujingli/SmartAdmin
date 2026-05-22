<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('system_tenant')) {
            return;
        }

        Schema::create('system_tenant', function (Blueprint $table) {
            $table->addColumn('bigInteger', 'id', ['autoIncrement' => true, 'unsigned' => true])->comment('主键ID');
            $table->addColumn('string', 'code', ['length' => 50])->nullable()->default('')->comment('租户编码');
            $table->addColumn('string', 'name', ['length' => 100])->nullable()->default('')->comment('租户名称');
            $table->addColumn('string', 'contact_name', ['length' => 50])->nullable()->default('')->comment('联系人姓名');
            $table->addColumn('string', 'contact_phone', ['length' => 30])->nullable()->default('')->comment('联系电话');
            $table->addColumn('string', 'contact_email', ['length' => 100])->nullable()->default('')->comment('联系邮箱');
            $table->addColumn('string', 'package_code', ['length' => 50])->nullable()->default('basic')->comment('套餐编码');
            $table->addColumn('dateTime', 'expired_at', [])->nullable()->comment('到期时间');
            $table->addColumn('bigInteger', 'status', [])->nullable()->default(1)->comment('状态(1启用,0禁用)');
            $table->addColumn('string', 'remark', ['length' => 255])->nullable()->default('')->comment('备注');
            $table->addColumn('bigInteger', 'created_by', [])->nullable()->default(0)->comment('创建者');
            $table->addColumn('bigInteger', 'updated_by', [])->nullable()->default(0)->comment('更新者');
            $table->addColumn('dateTime', 'deleted_at', [])->nullable()->comment('删除时间');
            $table->addColumn('dateTime', 'created_at', [])->nullable()->comment('创建时间');
            $table->addColumn('dateTime', 'updated_at', [])->nullable()->comment('更新时间');
            $table->index(['deleted_at'], 'idx_st_80ca_deleted_at');
            $table->index(['expired_at'], 'idx_st_80ca_expired_at');
            $table->index(['status'], 'idx_st_80ca_status');
            $table->unique(['code'], 'uni_st_80ca_code');
            $table->comment('租户管理表');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_tenant');
    }
};
