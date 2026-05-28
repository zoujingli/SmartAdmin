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
        if (!Schema::hasTable('wechat_client_user')) {
            Schema::create('wechat_client_user', function (Blueprint $table) {
                $this->base($table);
                $table->addColumn('bigInteger', 'tenant_id')->nullable()->default(0)->comment('租户ID');
                $table->addColumn('bigInteger', 'account_id')->nullable()->default(0)->comment('接口账号ID');
                $table->addColumn('string', 'appid', ['length' => 64])->nullable()->default('')->comment('微信 AppID');
                $table->addColumn('string', 'openid', ['length' => 100])->nullable()->default('')->comment('粉丝 OpenID');
                $table->addColumn('string', 'unionid', ['length' => 100])->nullable()->default('')->comment('UnionID');
                $table->addColumn('string', 'nickname', ['length' => 120])->nullable()->default('')->comment('昵称');
                $table->addColumn('string', 'avatar', ['length' => 500])->nullable()->default('')->comment('头像');
                $table->addColumn('bigInteger', 'subscribe')->nullable()->default(0)->comment('是否关注');
                $table->addColumn('timestamp', 'subscribe_time')->nullable()->comment('关注时间');
                $table->addColumn('string', 'remark', ['length' => 255])->nullable()->default('')->comment('备注');
                $table->addColumn('text', 'tagids')->nullable()->comment('标签ID JSON');
                $table->addColumn('text', 'raw_payload')->nullable()->comment('官方原始数据 JSON');
                $this->audit($table, true);
                $table->unique(['appid', 'openid'], 'uni_wcli_user_appid_openid');
                $table->index(['tenant_id', 'account_id'], 'idx_wcli_user_tenant_account');
                $table->index(['subscribe'], 'idx_wcli_user_subscribe');
                $table->comment('微信公众号粉丝/用户表');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wechat_client_user');
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
