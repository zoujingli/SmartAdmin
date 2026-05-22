<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('wechat_client_payment_refund')) {
            Schema::create('wechat_client_payment_refund', function (Blueprint $table) {
                $this->base($table);
                $table->addColumn('bigInteger', 'tenant_id')->nullable()->default(0)->comment('租户ID');
                $table->addColumn('bigInteger', 'merchant_id')->nullable()->default(0)->comment('支付商户ID');
                $table->addColumn('bigInteger', 'order_id')->nullable()->default(0)->comment('订单ID');
                $table->addColumn('string', 'order_no', ['length' => 100])->nullable()->default('')->comment('业务订单号');
                $table->addColumn('string', 'out_trade_no', ['length' => 100])->nullable()->default('')->comment('商户订单号');
                $table->addColumn('string', 'out_refund_no', ['length' => 100])->nullable()->default('')->comment('商户退款号');
                $table->addColumn('string', 'refund_id', ['length' => 100])->nullable()->default('')->comment('微信退款单号');
                $table->addColumn('bigInteger', 'amount_total')->nullable()->default(0)->comment('订单金额(分)');
                $table->addColumn('bigInteger', 'amount_refund')->nullable()->default(0)->comment('退款金额(分)');
                $table->addColumn('string', 'notify_url', ['length' => 500])->nullable()->default('')->comment('退款通知地址');
                $table->addColumn('timestamp', 'refunded_at')->nullable()->comment('退款成功时间');
                $table->addColumn('string', 'refund_status', ['length' => 40])->nullable()->default('PROCESSING')->comment('退款状态');
                $table->addColumn('string', 'reason', ['length' => 200])->nullable()->default('')->comment('退款原因');
                $table->addColumn('string', 'fail_reason', ['length' => 500])->nullable()->default('')->comment('退款失败原因');
                $table->addColumn('text', 'raw_payload')->nullable()->comment('官方原始数据 JSON');
                $this->audit($table, true);
                $table->unique(['out_refund_no'], 'uni_wcli_payment_refund_no');
                $table->index(['order_no'], 'idx_wcli_payment_refund_biz_no');
                $table->index(['tenant_id', 'merchant_id'], 'idx_wcli_payment_refund_tenant_merchant');
                $table->index(['refund_status'], 'idx_wcli_payment_refund_status');
                $table->comment('微信支付退款表');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wechat_client_payment_refund');
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
