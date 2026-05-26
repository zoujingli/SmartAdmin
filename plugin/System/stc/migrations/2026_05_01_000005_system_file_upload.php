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
        if (Schema::hasTable('system_file_upload')) {
            return;
        }

        Schema::create('system_file_upload', function (Blueprint $table) {
            $table->addColumn('bigInteger', 'id', ['autoIncrement' => true, 'unsigned' => true])->comment('主键ID');
            $table->addColumn('string', 'session_id', ['length' => 64])->comment('上传会话编号');
            $table->addColumn('string', 'scene', ['length' => 20])->comment('上传场景(image图片,file文件,video视频或业务自定义)');
            $table->addColumn('string', 'driver', ['length' => 20])->comment('上传通道(local本地,oss阿里云OSS,qiniu七牛云,cos腾讯云COS,alist AList)');
            $table->addColumn('string', 'transport', ['length' => 30])->comment('上传方式(relay-single服务端单文件,relay-chunk服务端分片,direct-single客户端直传,direct-multipart客户端分片直传)');
            $table->addColumn('string', 'status', ['length' => 20])->comment('会话状态(initialized初始化,uploading上传中,aborted已中止)');
            $table->addColumn('string', 'origin_name', ['length' => 255])->comment('原始文件名');
            $table->addColumn('string', 'suffix', ['length' => 20])->nullable()->comment('文件后缀');
            $table->addColumn('string', 'mime_type', ['length' => 255])->nullable()->comment('MIME 类型');
            $table->addColumn('bigInteger', 'size_byte', [])->default(0)->comment('文件大小(字节)');
            $table->addColumn('string', 'hash', ['length' => 64])->nullable()->comment('文件哈希');
            $table->addColumn('string', 'object_name', ['length' => 255])->comment('对象文件名');
            $table->addColumn('string', 'storage_path', ['length' => 120])->comment('对象路径');
            $table->addColumn('string', 'upload_id', ['length' => 255])->nullable()->comment('OSS Multipart Upload ID');
            $table->addColumn('integer', 'part_size', [])->default(0)->comment('分片大小(字节)');
            $table->addColumn('integer', 'part_count', [])->default(1)->comment('分片数量');
            $table->addColumn('text', 'parts', [])->nullable()->comment('分片状态 JSON');
            $table->addColumn('string', 'complete_token', ['length' => 64])->comment('完成令牌');
            $table->addColumn('integer', 'file_id', [])->nullable()->comment('完成后的文件ID');
            $table->addColumn('dateTime', 'expired_at', [])->nullable()->comment('过期时间');
            $table->addColumn('dateTime', 'completed_at', [])->nullable()->comment('完成时间');
            $table->addColumn('dateTime', 'aborted_at', [])->nullable()->comment('中止时间');
            $table->addColumn('bigInteger', 'created_by', [])->nullable()->comment('创建者');
            $table->addColumn('bigInteger', 'updated_by', [])->nullable()->comment('更新者');
            $table->addColumn('timestamp', 'created_at', [])->nullable()->comment('创建时间');
            $table->addColumn('timestamp', 'updated_at', [])->nullable()->comment('更新时间');
            $table->addColumn('bigInteger', 'tenant_id', [])->nullable()->default(0)->comment('租户ID');
            $table->index(['scene', 'driver'], 'idx_sfu_b274_scene_58970328');
            $table->index(['status'], 'idx_sfu_b274_status');
            $table->index(['tenant_id'], 'idx_sfu_b274_tenant_id');
            $table->unique(['session_id'], 'uni_sfu_b274_session_id');
            $table->comment('系统文件上传会话表');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_file_upload');
    }
};
