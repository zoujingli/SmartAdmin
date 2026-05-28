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
        if (!Schema::hasTable('wechat_client_media')) {
            Schema::create('wechat_client_media', function (Blueprint $table) {
                $this->base($table);
                $table->addColumn('bigInteger', 'tenant_id')->nullable()->default(0)->comment('租户ID');
                $table->addColumn('bigInteger', 'account_id')->nullable()->default(0)->comment('接口账号ID');
                $table->addColumn('string', 'media_id', ['length' => 180])->nullable()->default('')->comment('微信素材 MediaID');
                $table->addColumn('string', 'media_type', ['length' => 30])->nullable()->default('image')->comment('素材类型');
                $table->addColumn('string', 'name', ['length' => 180])->nullable()->default('')->comment('素材名称');
                $table->addColumn('string', 'url', ['length' => 500])->nullable()->default('')->comment('素材地址');
                $table->addColumn('bigInteger', 'file_id')->nullable()->default(0)->comment('本地文件ID');
                $table->addColumn('string', 'file_url', ['length' => 500])->nullable()->default('')->comment('本地或远程文件地址');
                $table->addColumn('text', 'raw_payload')->nullable()->comment('官方原始数据 JSON');
                $this->audit($table, true);
                $table->index(['tenant_id', 'account_id'], 'idx_wcli_media_tenant_account');
                $table->index(['media_type'], 'idx_wcli_media_type');
                $table->comment('微信素材表');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wechat_client_media');
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
