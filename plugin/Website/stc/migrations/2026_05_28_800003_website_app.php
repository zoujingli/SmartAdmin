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
        if (Schema::hasTable('website_app')) {
            return;
        }

        Schema::create('website_app', function (Blueprint $table): void {
            $table->addColumn('bigInteger', 'id', ['autoIncrement' => true, 'unsigned' => true])->comment('主键ID');
            $table->addColumn('bigInteger', 'tenant_id')->nullable()->default(0)->comment('租户ID');
            $table->addColumn('bigInteger', 'site_id')->nullable()->default(0)->comment('站点ID');
            $table->addColumn('string', 'name', ['length' => 120])->nullable()->default('')->comment('应用名称');
            $table->addColumn('string', 'app_id', ['length' => 80])->nullable()->default('')->comment('开放接口 AppID');
            $table->addColumn('string', 'app_key', ['length' => 1000])->nullable()->default('')->comment('开放接口 AppKey 密文');
            $table->addColumn('text', 'scopes')->nullable()->comment('接口权限范围 JSON');
            $table->addColumn('text', 'ip_whitelist')->nullable()->comment('IP 白名单 JSON');
            $table->addColumn('bigInteger', 'rate_limit')->nullable()->default(60)->comment('每分钟限流次数');
            $table->addColumn('timestamp', 'last_used_at')->nullable()->comment('最后调用时间');
            $table->addColumn('string', 'last_used_ip', ['length' => 60])->nullable()->default('')->comment('最后调用 IP');
            $table->addColumn('bigInteger', 'status')->nullable()->default(1)->comment('状态(1启用,0禁用)');
            $table->addColumn('string', 'remark', ['length' => 1000])->nullable()->default('')->comment('备注');
            $table->addColumn('bigInteger', 'created_by')->nullable()->default(0)->comment('创建者');
            $table->addColumn('bigInteger', 'updated_by')->nullable()->default(0)->comment('更新者');
            $table->addColumn('timestamp', 'created_at')->nullable()->comment('创建时间');
            $table->addColumn('timestamp', 'updated_at')->nullable()->comment('更新时间');
            $table->addColumn('timestamp', 'deleted_at')->nullable()->comment('删除时间');
            $table->unique(['app_id'], 'uni_website_app_app_id');
            $table->index(['tenant_id', 'site_id', 'status'], 'idx_website_app_tenant_site_status');
            $table->index(['site_id'], 'idx_website_app_site');
            $table->index(['last_used_at'], 'idx_website_app_last_used_at');
            $table->comment('官网开放接口应用表');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_app');
    }
};
