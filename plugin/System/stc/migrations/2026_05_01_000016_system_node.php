<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('system_node')) {
            return;
        }

        Schema::create('system_node', function (Blueprint $table) {
            $table->addColumn('bigInteger', 'id', ['autoIncrement' => true, 'unsigned' => true])->comment('主键ID');
            $table->addColumn('string', 'node', ['length' => 150])->nullable()->default('')->comment('权限节点编码');
            $table->addColumn('string', 'name', ['length' => 100])->nullable()->default('')->comment('节点名称');
            $table->addColumn('string', 'type', ['length' => 30])->nullable()->default('')->comment('节点类型(check授权校验,login登录校验)');
            $table->addColumn('string', 'source', ['length' => 30])->nullable()->default('')->comment('注册来源(annotation注解,menu菜单,system系统)');
            $table->addColumn('string', 'ref', ['length' => 255])->nullable()->default('')->comment('来源引用');
            $table->addColumn('bigInteger', 'status', [])->nullable()->default(1)->comment('状态(1启用,0禁用)');
            $table->addColumn('text', 'meta', [])->nullable()->comment('元数据(JSON)');
            $table->addColumn('bigInteger', 'created_by', [])->nullable()->default(0)->comment('创建者');
            $table->addColumn('bigInteger', 'updated_by', [])->nullable()->default(0)->comment('更新者');
            $table->addColumn('timestamp', 'created_at', [])->nullable()->comment('创建时间');
            $table->addColumn('timestamp', 'updated_at', [])->nullable()->comment('更新时间');
            $table->index(['source'], 'idx_sn_62e2_source');
            $table->index(['status'], 'idx_sn_62e2_status');
            $table->unique(['node'], 'uni_sn_62e2_node');
            $table->comment('系统权限节点表');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_node');
    }
};
