<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('system_logs_action')) {
            return;
        }

        Schema::create('system_logs_action', function (Blueprint $table) {
            $table->addColumn('bigInteger', 'id', ['autoIncrement' => true, 'unsigned' => true])->comment('主键ID');
            $table->addColumn('bigInteger', 'tenant_id', [])->nullable()->default(0)->comment('租户ID');
            $table->addColumn('string', 'username', ['length' => 20])->nullable()->default('')->comment('操作用户名');
            $table->addColumn('string', 'method', ['length' => 20])->nullable()->default('')->comment('HTTP请求方法(GET,POST,PUT,DELETE等)');
            $table->addColumn('string', 'router', ['length' => 500])->nullable()->default('')->comment('请求路由');
            $table->addColumn('string', 'name', ['length' => 30])->nullable()->default('')->comment('操作名称');
            $table->addColumn('string', 'remark', ['length' => 200])->nullable()->default('')->comment('操作摘要');
            $table->addColumn('string', 'ip', ['length' => 200])->nullable()->default('')->comment('请求IP');
            $table->addColumn('string', 'ip_location', ['length' => 200])->nullable()->default('')->comment('IP归属地');
            $table->addColumn('string', 'os', ['length' => 200])->nullable()->default('')->comment('客户端操作系统');
            $table->addColumn('string', 'browser', ['length' => 200])->nullable()->default('')->comment('客户端浏览器');
            $table->addColumn('text', 'request_data', [])->nullable()->comment('脱敏后的请求内容(JSON)');
            $table->addColumn('string', 'response_code', ['length' => 5])->nullable()->default('')->comment('业务响应码(200成功,401未认证,403无权限,404路由不存在,500业务失败)');
            $table->addColumn('text', 'response_data', [])->nullable()->comment('脱敏后的响应内容(JSON)');
            $table->addColumn('bigInteger', 'created_by', [])->nullable()->default(0)->comment('创建者');
            $table->addColumn('bigInteger', 'updated_by', [])->nullable()->default(0)->comment('更新者');
            $table->addColumn('timestamp', 'created_at', [])->nullable()->comment('创建时间');
            $table->addColumn('timestamp', 'updated_at', [])->nullable()->comment('更新时间');
            $table->addColumn('timestamp', 'deleted_at', [])->nullable()->comment('删除时间');
            $table->index(['deleted_at'], 'idx_sla_62c8_deleted_at');
            $table->index(['created_at'], 'idx_sla_62c8_created_at');
            $table->index(['response_code'], 'idx_sla_62c8_response_code');
            $table->index(['tenant_id'], 'idx_sla_62c8_tenant_id');
            $table->comment('系统操作日志表');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_logs_action');
    }
};
