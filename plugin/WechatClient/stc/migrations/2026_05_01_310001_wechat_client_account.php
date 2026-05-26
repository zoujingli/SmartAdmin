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
        if (!Schema::hasTable('wechat_client_account')) {
            Schema::create('wechat_client_account', function (Blueprint $table) {
                $this->base($table);
                $table->addColumn('bigInteger', 'tenant_id')->nullable()->default(0)->comment('租户ID');
                $table->addColumn('string', 'appid', ['length' => 64])->nullable()->default('')->comment('微信 AppID');
                $table->addColumn('string', 'name', ['length' => 120])->nullable()->default('')->comment('账号名称');
                $table->addColumn('string', 'account_type', ['length' => 30])->nullable()->default('official_account')->comment('账号类型');
                $table->addColumn('bigInteger', 'service_mode')->nullable()->default(0)->comment('接入模式(0直连,1开放平台授权)');
                $table->addColumn('text', 'appsecret')->nullable()->comment('AppSecret 密文');
                $table->addColumn('text', 'token')->nullable()->comment('消息 Token 密文');
                $table->addColumn('text', 'encodingaeskey')->nullable()->comment('消息加解密 Key 密文');
                $table->addColumn('text', 'access_token')->nullable()->comment('AccessToken 密文');
                $table->addColumn('text', 'refresh_token')->nullable()->comment('RefreshToken 密文');
                $table->addColumn('bigInteger', 'expires_at')->nullable()->default(0)->comment('Token 过期时间');
                $table->addColumn('text', 'extra')->nullable()->comment('扩展配置 JSON');
                $this->audit($table, true);
                $table->unique(['appid'], 'uni_wcli_acc_appid');
                $table->index(['tenant_id'], 'idx_wcli_acc_tenant');
                $table->index(['status'], 'idx_wcli_acc_status');
                $table->comment('微信租户接口账号表');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wechat_client_account');
    }

    private function base(Blueprint $table): void
    {
        $table->addColumn('bigInteger', 'id', ['autoIncrement' => true, 'unsigned' => true])->comment('主键ID');
        $table->addColumn('bigInteger', 'status')->nullable()->default(1)->comment('状态(1启用,0禁用)');
    }

    private function audit(Blueprint $table, bool $softDelete): void
    {
        $table->addColumn('bigInteger', 'created_by')->nullable()->default(0)->comment('创建者');
        $table->addColumn('bigInteger', 'updated_by')->nullable()->default(0)->comment('更新者');
        $table->addColumn('timestamp', 'created_at')->nullable()->comment('创建时间');
        $table->addColumn('timestamp', 'updated_at')->nullable()->comment('更新时间');
        if ($softDelete) {
            $table->addColumn('timestamp', 'deleted_at')->nullable()->comment('删除时间');
        }
    }
};
