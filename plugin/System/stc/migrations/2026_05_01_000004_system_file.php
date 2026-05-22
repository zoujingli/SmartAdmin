<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('system_file')) {
            return;
        }

        Schema::create('system_file', function (Blueprint $table) {
            $table->addColumn('bigInteger', 'id', ['autoIncrement' => true, 'unsigned' => true])->comment('主键ID');
            $table->addColumn('string', 'scene', ['length' => 20])->nullable()->comment('上传场景(image图片,file文件,video视频或业务自定义)');
            $table->addColumn('string', 'driver', ['length' => 20])->nullable()->comment('上传通道(local本地,oss阿里云OSS,qiniu七牛云,cos腾讯云COS,alist AList)');
            $table->addColumn('string', 'url', ['length' => 255])->nullable()->comment('文件地址');
            $table->addColumn('string', 'hash', ['length' => 64])->nullable()->comment('文件哈希');
            $table->addColumn('string', 'suffix', ['length' => 10])->nullable()->comment('文件后缀');
            $table->addColumn('integer', 'storage_mode', [])->nullable()->comment('存储方式(1本地,2阿里云OSS,3七牛云,4腾讯云COS,5Alist)');
            $table->addColumn('string', 'origin_name', ['length' => 255])->nullable()->comment('原始文件名');
            $table->addColumn('string', 'object_name', ['length' => 50])->nullable()->comment('存储文件名');
            $table->addColumn('string', 'storage_path', ['length' => 100])->nullable()->comment('存储路径');
            $table->addColumn('string', 'mime_type', ['length' => 255])->nullable()->comment('MIME类型');
            $table->addColumn('bigInteger', 'size_byte', [])->nullable()->comment('文件大小(字节)');
            $table->addColumn('string', 'size_info', ['length' => 50])->nullable()->comment('文件大小描述');
            $table->addColumn('string', 'remark', ['length' => 255])->nullable()->comment('备注');
            $table->addColumn('bigInteger', 'created_by', [])->nullable()->comment('创建者');
            $table->addColumn('bigInteger', 'updated_by', [])->nullable()->comment('更新者');
            $table->addColumn('timestamp', 'created_at', [])->nullable()->comment('创建时间');
            $table->addColumn('timestamp', 'updated_at', [])->nullable()->comment('更新时间');
            $table->addColumn('timestamp', 'deleted_at', [])->nullable()->comment('删除时间');
            $table->addColumn('bigInteger', 'tenant_id', [])->nullable()->default(0)->comment('租户ID');
            $table->index(['deleted_at'], 'idx_sf_c455_deleted_at');
            $table->index(['driver', 'hash'], 'idx_sf_c455_driver_843a2f44');
            $table->index(['driver', 'storage_path', 'object_name'], 'idx_sf_c455_driver_c2138686');
            $table->index(['scene'], 'idx_sf_c455_scene');
            $table->index(['storage_path'], 'idx_sf_c455_storage_path');
            $table->index(['tenant_id'], 'idx_sf_c455_tenant_id');
            $table->comment('系统上传文件表');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_file');
    }
};
