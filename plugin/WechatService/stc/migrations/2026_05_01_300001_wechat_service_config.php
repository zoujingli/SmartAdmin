<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('wechat_service_config')) {
            Schema::create('wechat_service_config', function (Blueprint $table) {
                $table->addColumn('bigInteger', 'id', ['autoIncrement' => true, 'unsigned' => true])->comment('主键ID');
                $table->addColumn('string', 'name', ['length' => 100])->nullable()->default('')->comment('配置名称');
                $table->addColumn('string', 'component_appid', ['length' => 64])->nullable()->default('')->comment('第三方平台 AppID');
                $table->addColumn('text', 'component_appsecret')->nullable()->comment('第三方平台 AppSecret 密文');
                $table->addColumn('text', 'component_token')->nullable()->comment('消息校验 Token 密文');
                $table->addColumn('text', 'component_encodingaeskey')->nullable()->comment('消息加解密 Key 密文');
                $table->addColumn('text', 'component_verify_ticket')->nullable()->comment('开放平台推送 Ticket 密文');
                $table->addColumn('text', 'component_access_token')->nullable()->comment('第三方平台 AccessToken 密文');
                $table->addColumn('bigInteger', 'component_expires_at')->nullable()->default(0)->comment('第三方平台 Token 过期时间');
                $table->addColumn('bigInteger', 'status')->nullable()->default(1)->comment('状态(1启用,0禁用)');
                $table->addColumn('string', 'remark', ['length' => 255])->nullable()->default('')->comment('备注');
                $table->addColumn('bigInteger', 'created_by')->nullable()->default(0)->comment('创建者');
                $table->addColumn('bigInteger', 'updated_by')->nullable()->default(0)->comment('更新者');
                $table->addColumn('timestamp', 'created_at')->nullable()->comment('创建时间');
                $table->addColumn('timestamp', 'updated_at')->nullable()->comment('更新时间');
                $table->addColumn('timestamp', 'deleted_at')->nullable()->comment('删除时间');
                $table->unique(['component_appid'], 'uni_wsvc_cfg_appid');
                $table->index(['status'], 'idx_wsvc_cfg_status');
                $table->comment('微信开放平台配置表');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wechat_service_config');
    }
};
