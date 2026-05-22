<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('wechat_client_payment_order')) {
            Schema::create('wechat_client_payment_order', function (Blueprint $table) {
                $this->base($table);
                $table->addColumn('bigInteger', 'tenant_id')->nullable()->default(0)->comment('租户ID');
                $table->addColumn('bigInteger', 'merchant_id')->nullable()->default(0)->comment('支付商户ID');
                $table->addColumn('string', 'appid', ['length' => 64])->nullable()->default('')->comment('支付 AppID');
                $table->addColumn('string', 'mch_id', ['length' => 64])->nullable()->default('')->comment('商户号');
                $table->addColumn('string', 'order_no', ['length' => 100])->nullable()->default('')->comment('业务订单号');
                $table->addColumn('string', 'out_trade_no', ['length' => 100])->nullable()->default('')->comment('商户订单号');
                $table->addColumn('string', 'transaction_id', ['length' => 100])->nullable()->default('')->comment('微信支付订单号');
                $table->addColumn('string', 'trade_type', ['length' => 30])->nullable()->default('JSAPI')->comment('交易类型');
                $table->addColumn('string', 'description', ['length' => 200])->nullable()->default('')->comment('商品描述');
                $table->addColumn('bigInteger', 'amount_total')->nullable()->default(0)->comment('订单金额(分)');
                $table->addColumn('string', 'payer_openid', ['length' => 100])->nullable()->default('')->comment('付款人 OpenID');
                $table->addColumn('string', 'notify_url', ['length' => 500])->nullable()->default('')->comment('支付通知地址');
                $table->addColumn('string', 'prepayment_id', ['length' => 120])->nullable()->default('')->comment('微信预支付ID');
                $table->addColumn('timestamp', 'paid_at')->nullable()->comment('支付成功时间');
                $table->addColumn('string', 'trade_state', ['length' => 40])->nullable()->default('CREATED')->comment('交易状态');
                $table->addColumn('text', 'raw_payload')->nullable()->comment('官方原始数据 JSON');
                $this->audit($table, true);
                $table->unique(['out_trade_no'], 'uni_wcli_payment_order_no');
                $table->index(['order_no'], 'idx_wcli_payment_order_biz_no');
                $table->index(['tenant_id', 'merchant_id'], 'idx_wcli_payment_order_tenant_merchant');
                $table->index(['trade_state'], 'idx_wcli_payment_order_state');
                $table->comment('微信支付订单表');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wechat_client_payment_order');
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
