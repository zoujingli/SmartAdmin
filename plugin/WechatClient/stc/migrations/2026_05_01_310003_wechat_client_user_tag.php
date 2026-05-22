<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('wechat_client_user_tag')) {
            Schema::create('wechat_client_user_tag', function (Blueprint $table) {
                $this->base($table);
                $table->addColumn('bigInteger', 'tenant_id')->nullable()->default(0)->comment('租户ID');
                $table->addColumn('bigInteger', 'account_id')->nullable()->default(0)->comment('接口账号ID');
                $table->addColumn('string', 'appid', ['length' => 64])->nullable()->default('')->comment('微信 AppID');
                $table->addColumn('bigInteger', 'tag_id')->nullable()->default(0)->comment('微信标签ID');
                $table->addColumn('string', 'name', ['length' => 80])->nullable()->default('')->comment('标签名称');
                $table->addColumn('bigInteger', 'count')->nullable()->default(0)->comment('粉丝数量');
                $this->audit($table, true);
                $table->unique(['appid', 'tag_id'], 'uni_wcli_user_tag_appid_tag');
                $table->index(['tenant_id', 'account_id'], 'idx_wcli_user_tag_tenant_account');
                $table->comment('微信公众号用户标签表');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wechat_client_user_tag');
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
