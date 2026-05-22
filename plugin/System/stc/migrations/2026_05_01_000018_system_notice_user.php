<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('system_notice_user')) {
            return;
        }

        Schema::create('system_notice_user', function (Blueprint $table) {
            $table->addColumn('bigInteger', 'id', ['autoIncrement' => true, 'unsigned' => true])->comment('主键ID');
            $table->addColumn('bigInteger', 'notice_id', ['unsigned' => true])->comment('公告ID');
            $table->addColumn('bigInteger', 'user_id', ['unsigned' => true])->comment('收件用户ID');
            $table->addColumn('bigInteger', 'is_read', [])->nullable()->default(0)->comment('是否已读(1已读,0未读)');
            $table->addColumn('timestamp', 'read_at', [])->nullable()->comment('已读时间');
            $table->addColumn('timestamp', 'archived_at', [])->nullable()->comment('归档时间');
            $table->addColumn('timestamp', 'created_at', [])->nullable()->comment('创建时间');
            $table->addColumn('timestamp', 'updated_at', [])->nullable()->comment('更新时间');
            $table->addColumn('bigInteger', 'tenant_id', [])->nullable()->default(0)->comment('租户ID');
            $table->index(['tenant_id'], 'idx_snu_6526_tenant_id');
            $table->index(['notice_id'], 'idx_snu_6526_notice_id');
            $table->index(['user_id', 'archived_at'], 'idx_snu_6526_user_id_349898cb');
            $table->index(['user_id', 'is_read'], 'idx_snu_6526_user_id_41d78b4a');
            $table->unique(['notice_id', 'user_id'], 'uni_snu_6526_notice_id_6dd26c86');
            $table->comment('系统公告收件人表');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_notice_user');
    }
};
