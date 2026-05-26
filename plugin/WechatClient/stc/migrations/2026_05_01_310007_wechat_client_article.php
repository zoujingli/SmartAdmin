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
        if (!Schema::hasTable('wechat_client_article')) {
            Schema::create('wechat_client_article', function (Blueprint $table) {
                $this->base($table);
                $table->addColumn('bigInteger', 'tenant_id')->nullable()->default(0)->comment('租户ID');
                $table->addColumn('bigInteger', 'account_id')->nullable()->default(0)->comment('接口账号ID');
                $table->addColumn('string', 'title', ['length' => 180])->nullable()->default('')->comment('文章标题');
                $table->addColumn('string', 'author', ['length' => 80])->nullable()->default('')->comment('作者');
                $table->addColumn('string', 'thumb_media_id', ['length' => 180])->nullable()->default('')->comment('封面 MediaID');
                $table->addColumn('string', 'thumb_url', ['length' => 500])->nullable()->default('')->comment('封面地址');
                $table->addColumn('text', 'content')->nullable()->comment('正文内容');
                $table->addColumn('text', 'digest')->nullable()->comment('摘要');
                $table->addColumn('string', 'content_source_url', ['length' => 500])->nullable()->default('')->comment('原文链接');
                $table->addColumn('string', 'draft_media_id', ['length' => 180])->nullable()->default('')->comment('草稿 MediaID');
                $table->addColumn('string', 'publish_id', ['length' => 180])->nullable()->default('')->comment('发布任务ID');
                $table->addColumn('string', 'publish_status', ['length' => 30])->nullable()->default('draft')->comment('发布状态');
                $table->addColumn('text', 'raw_payload')->nullable()->comment('官方原始数据 JSON');
                $this->audit($table, true);
                $table->index(['tenant_id', 'account_id'], 'idx_wcli_article_tenant_account');
                $table->index(['publish_status'], 'idx_wcli_article_publish_status');
                $table->comment('微信图文草稿与发布表');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wechat_client_article');
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
