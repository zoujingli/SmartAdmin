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
        if (!Schema::hasTable('wechat_service_auth')) {
            Schema::create('wechat_service_auth', function (Blueprint $table) {
                $table->addColumn('bigInteger', 'id', ['autoIncrement' => true, 'unsigned' => true])->comment('主键ID');
                $table->addColumn('bigInteger', 'tenant_id')->nullable()->default(0)->comment('归属租户ID');
                $table->addColumn('string', 'authorizer_appid', ['length' => 64])->nullable()->default('')->comment('授权账号 AppID');
                $table->addColumn('string', 'nick_name', ['length' => 120])->nullable()->default('')->comment('授权账号昵称');
                $table->addColumn('string', 'account_type', ['length' => 30])->nullable()->default('official_account')->comment('账号类型');
                $table->addColumn('bigInteger', 'service_type')->nullable()->default(0)->comment('服务类型');
                $table->addColumn('bigInteger', 'verify_type')->nullable()->default(0)->comment('认证类型');
                $table->addColumn('string', 'principal_name', ['length' => 180])->nullable()->default('')->comment('主体名称');
                $table->addColumn('string', 'qrcode_url', ['length' => 500])->nullable()->default('')->comment('二维码地址');
                $table->addColumn('text', 'authorizer_access_token')->nullable()->comment('授权账号 AccessToken 密文');
                $table->addColumn('text', 'authorizer_refresh_token')->nullable()->comment('授权账号 RefreshToken 密文');
                $table->addColumn('bigInteger', 'expires_at')->nullable()->default(0)->comment('授权账号 Token 过期时间');
                $table->addColumn('text', 'permissions')->nullable()->comment('授权能力 JSON');
                $table->addColumn('text', 'raw_payload')->nullable()->comment('官方原始数据 JSON');
                $table->addColumn('bigInteger', 'total')->nullable()->default(0)->comment('网关调用次数');
                $table->addColumn('bigInteger', 'status')->nullable()->default(1)->comment('状态(1启用,0禁用)');
                $table->addColumn('timestamp', 'auth_time')->nullable()->comment('授权时间');
                $table->addColumn('bigInteger', 'created_by')->nullable()->default(0)->comment('创建者');
                $table->addColumn('bigInteger', 'updated_by')->nullable()->default(0)->comment('更新者');
                $table->addColumn('timestamp', 'created_at')->nullable()->comment('创建时间');
                $table->addColumn('timestamp', 'updated_at')->nullable()->comment('更新时间');
                $table->addColumn('timestamp', 'deleted_at')->nullable()->comment('删除时间');
                $table->unique(['authorizer_appid'], 'uni_wsvc_auth_appid');
                $table->index(['tenant_id'], 'idx_wsvc_auth_tenant');
                $table->index(['status'], 'idx_wsvc_auth_status');
                $table->comment('微信开放平台授权账号表');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wechat_service_auth');
    }
};
