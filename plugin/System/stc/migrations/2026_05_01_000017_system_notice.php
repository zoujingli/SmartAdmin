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
        if (Schema::hasTable('system_notice')) {
            return;
        }

        Schema::create('system_notice', function (Blueprint $table) {
            $table->addColumn('bigInteger', 'id', ['autoIncrement' => true, 'unsigned' => true])->comment('主键ID');
            $table->addColumn('string', 'title', ['length' => 120])->nullable()->default('')->comment('公告标题');
            $table->addColumn('text', 'content', [])->nullable()->comment('公告内容');
            $table->addColumn('string', 'level', ['length' => 20])->nullable()->default('info')->comment('公告级别(info信息,success成功,warning警告,error错误)');
            $table->addColumn('bigInteger', 'status', [])->nullable()->default(1)->comment('状态(1启用,0禁用)');
            $table->addColumn('timestamp', 'published_at', [])->nullable()->comment('发布时间');
            $table->addColumn('timestamp', 'expired_at', [])->nullable()->comment('过期时间');
            $table->addColumn('string', 'link', ['length' => 255])->nullable()->default('')->comment('附加跳转链接');
            $table->addColumn('bigInteger', 'created_by', [])->nullable()->default(0)->comment('创建者');
            $table->addColumn('bigInteger', 'updated_by', [])->nullable()->default(0)->comment('更新者');
            $table->addColumn('timestamp', 'created_at', [])->nullable()->comment('创建时间');
            $table->addColumn('timestamp', 'updated_at', [])->nullable()->comment('更新时间');
            $table->addColumn('timestamp', 'deleted_at', [])->nullable()->comment('删除时间');
            $table->addColumn('bigInteger', 'tenant_id', [])->nullable()->default(0)->comment('租户ID');
            $table->index(['tenant_id'], 'idx_sn_b705_tenant_id');
            $table->index(['deleted_at'], 'idx_sn_b705_deleted_at');
            $table->index(['expired_at'], 'idx_sn_b705_expired_at');
            $table->index(['level'], 'idx_sn_b705_level');
            $table->index(['published_at'], 'idx_sn_b705_published_at');
            $table->index(['status'], 'idx_sn_b705_status');
            $table->comment('系统公告表');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_notice');
    }
};
