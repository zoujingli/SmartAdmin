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
        if (!Schema::hasTable('wechat_client_payment_merchant')) {
            Schema::create('wechat_client_payment_merchant', function (Blueprint $table) {
                $this->base($table);
                $table->addColumn('bigInteger', 'tenant_id')->nullable()->default(0)->comment('租户ID');
                $table->addColumn('bigInteger', 'account_id')->nullable()->default(0)->comment('接口账号ID');
                $table->addColumn('string', 'appid', ['length' => 64])->nullable()->default('')->comment('支付 AppID');
                $table->addColumn('string', 'mch_id', ['length' => 64])->nullable()->default('')->comment('商户号');
                $table->addColumn('string', 'name', ['length' => 120])->nullable()->default('')->comment('商户名称');
                $table->addColumn('text', 'api_v3_key')->nullable()->comment('APIv3 Key 密文');
                $table->addColumn('text', 'merchant_serial')->nullable()->comment('商户证书序列号密文');
                $table->addColumn('text', 'merchant_private_key')->nullable()->comment('商户私钥密文');
                $table->addColumn('text', 'platform_public_key')->nullable()->comment('微信支付平台公钥密文');
                $table->addColumn('text', 'platform_serial')->nullable()->comment('平台证书序列号密文');
                $this->audit($table, true);
                $table->unique(['mch_id'], 'uni_wcli_payment_mch');
                $table->index(['tenant_id', 'account_id'], 'idx_wcli_payment_tenant_account');
                $table->comment('微信支付商户配置表');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wechat_client_payment_merchant');
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
