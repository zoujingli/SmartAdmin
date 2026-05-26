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
        if (!Schema::hasTable('wechat_service_logger')) {
            Schema::create('wechat_service_logger', function (Blueprint $table) {
                $table->addColumn('bigInteger', 'id', ['autoIncrement' => true, 'unsigned' => true])->comment('主键ID');
                $table->addColumn('string', 'event', ['length' => 80])->nullable()->default('')->comment('回调事件');
                $table->addColumn('string', 'appid', ['length' => 64])->nullable()->default('')->comment('相关 AppID');
                $table->addColumn('text', 'payload')->nullable()->comment('回调数据 JSON');
                $table->addColumn('bigInteger', 'status')->nullable()->default(1)->comment('处理状态(1成功,0失败)');
                $table->addColumn('string', 'message', ['length' => 500])->nullable()->default('')->comment('处理消息');
                $table->addColumn('timestamp', 'created_at')->nullable()->comment('创建时间');
                $table->addColumn('timestamp', 'updated_at')->nullable()->comment('更新时间');
                $table->index(['appid'], 'idx_wsvc_log_appid');
                $table->index(['event'], 'idx_wsvc_log_event');
                $table->comment('微信开放平台回调日志表');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wechat_service_logger');
    }
};
