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
        if (Schema::hasTable('system_user')) {
            return;
        }

        Schema::create('system_user', function (Blueprint $table) {
            $table->addColumn('bigInteger', 'id', ['autoIncrement' => true, 'unsigned' => true])->comment('主键ID');
            $table->addColumn('bigInteger', 'tenant_id', [])->nullable()->default(0)->comment('租户ID');
            $table->addColumn('string', 'username', ['length' => 20])->nullable()->default('')->comment('用户名');
            $table->addColumn('string', 'nickname', ['length' => 30])->nullable()->default('')->comment('用户昵称');
            $table->addColumn('string', 'phone', ['length' => 11])->nullable()->default('')->comment('手机号码');
            $table->addColumn('string', 'email', ['length' => 50])->nullable()->default('')->comment('邮箱地址');
            $table->addColumn('string', 'password', ['length' => 100])->nullable()->default('')->comment('密码哈希');
            $table->addColumn('string', 'avatar', ['length' => 255])->nullable()->default('')->comment('用户头像');
            $table->addColumn('string', 'signed', ['length' => 255])->nullable()->default('')->comment('个性签名');
            $table->addColumn('bigInteger', 'status', [])->nullable()->default(1)->comment('状态(1启用,0禁用)');
            $table->addColumn('string', 'remark', ['length' => 255])->nullable()->default('')->comment('备注');
            $table->addColumn('string', 'login_ip', ['length' => 45])->nullable()->default('')->comment('最后登录IP');
            $table->addColumn('timestamp', 'login_time', [])->nullable()->comment('最后登录时间');
            $table->addColumn('text', 'extra', [])->nullable()->comment('扩展数据(JSON)');
            $table->addColumn('bigInteger', 'created_by', [])->nullable()->default(0)->comment('创建者');
            $table->addColumn('bigInteger', 'updated_by', [])->nullable()->default(0)->comment('更新者');
            $table->addColumn('timestamp', 'created_at', [])->nullable()->comment('创建时间');
            $table->addColumn('timestamp', 'updated_at', [])->nullable()->comment('更新时间');
            $table->addColumn('timestamp', 'deleted_at', [])->nullable()->comment('删除时间');
            $table->index(['deleted_at'], 'idx_su_ddf2_deleted_at');
            $table->index(['status'], 'idx_su_ddf2_status');
            $table->index(['tenant_id'], 'idx_su_ddf2_tenant_id');
            $table->unique(['username'], 'uni_su_ddf2_username');
            $table->comment('系统用户表');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_user');
    }
};
