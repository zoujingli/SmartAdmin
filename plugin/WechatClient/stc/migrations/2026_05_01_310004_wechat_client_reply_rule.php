<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('wechat_client_reply_rule')) {
            Schema::create('wechat_client_reply_rule', function (Blueprint $table) {
                $this->base($table);
                $table->addColumn('bigInteger', 'tenant_id')->nullable()->default(0)->comment('租户ID');
                $table->addColumn('bigInteger', 'account_id')->nullable()->default(0)->comment('接口账号ID');
                $table->addColumn('string', 'rule_type', ['length' => 30])->nullable()->default('keyword')->comment('规则类型');
                $table->addColumn('string', 'keyword', ['length' => 120])->nullable()->default('')->comment('关键词');
                $table->addColumn('string', 'match_mode', ['length' => 20])->nullable()->default('contains')->comment('匹配模式');
                $table->addColumn('string', 'reply_type', ['length' => 30])->nullable()->default('text')->comment('回复类型');
                $table->addColumn('text', 'reply_content')->nullable()->comment('回复内容 JSON');
                $table->addColumn('bigInteger', 'delay_seconds')->nullable()->default(0)->comment('订阅延迟发送秒数');
                $table->addColumn('bigInteger', 'sort')->nullable()->default(0)->comment('排序');
                $this->audit($table, true);
                $table->index(['tenant_id', 'account_id'], 'idx_wcli_rule_tenant_account');
                $table->index(['rule_type', 'status'], 'idx_wcli_rule_type_status');
                $table->comment('微信自动回复规则表');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wechat_client_reply_rule');
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
