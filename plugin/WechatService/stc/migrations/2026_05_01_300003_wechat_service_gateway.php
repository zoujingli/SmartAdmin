<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('wechat_service_gateway')) {
            Schema::create('wechat_service_gateway', function (Blueprint $table) {
                $table->addColumn('bigInteger', 'id', ['autoIncrement' => true, 'unsigned' => true])->comment('主键ID');
                $table->addColumn('string', 'client_key', ['length' => 80])->nullable()->default('')->comment('网关调用 Key');
                $table->addColumn('text', 'client_secret')->nullable()->comment('网关调用 Secret 密文');
                $table->addColumn('string', 'name', ['length' => 100])->nullable()->default('')->comment('凭据名称');
                $table->addColumn('text', 'allowed_appids')->nullable()->comment('允许调用的授权 AppID JSON');
                $table->addColumn('bigInteger', 'total')->nullable()->default(0)->comment('调用次数');
                $table->addColumn('bigInteger', 'status')->nullable()->default(1)->comment('状态(1启用,0禁用)');
                $table->addColumn('string', 'remark', ['length' => 255])->nullable()->default('')->comment('备注');
                $table->addColumn('bigInteger', 'created_by')->nullable()->default(0)->comment('创建者');
                $table->addColumn('bigInteger', 'updated_by')->nullable()->default(0)->comment('更新者');
                $table->addColumn('timestamp', 'created_at')->nullable()->comment('创建时间');
                $table->addColumn('timestamp', 'updated_at')->nullable()->comment('更新时间');
                $table->addColumn('timestamp', 'deleted_at')->nullable()->comment('删除时间');
                $table->unique(['client_key'], 'uni_wsvc_gate_key');
                $table->index(['status'], 'idx_wsvc_gate_status');
                $table->comment('微信开放平台接口网关凭据表');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wechat_service_gateway');
    }
};
