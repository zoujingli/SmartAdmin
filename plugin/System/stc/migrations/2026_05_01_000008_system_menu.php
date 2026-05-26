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
        if (Schema::hasTable('system_menu')) {
            return;
        }

        Schema::create('system_menu', function (Blueprint $table) {
            $table->addColumn('bigInteger', 'id', ['autoIncrement' => true, 'unsigned' => true])->comment('主键ID');
            $table->addColumn('bigInteger', 'pid', [])->nullable()->default(0)->comment('父级菜单ID');
            $table->addColumn('string', 'level', ['length' => 500])->nullable()->default('')->comment('层级路径');
            $table->addColumn('string', 'name', ['length' => 50])->nullable()->default('')->comment('菜单名称');
            $table->addColumn('string', 'code', ['length' => 100])->nullable()->default('')->comment('菜单权限编码');
            $table->addColumn('string', 'icon', ['length' => 50])->nullable()->default('')->comment('菜单图标');
            $table->addColumn('string', 'type', ['length' => 50])->nullable()->default('')->comment('菜单类型(D目录,M菜单,B按钮,L外链,I内嵌页)');
            $table->addColumn('string', 'route', ['length' => 200])->nullable()->default('')->comment('前端路由地址');
            $table->addColumn('string', 'component', ['length' => 255])->nullable()->default('')->comment('前端路由组件');
            $table->addColumn('string', 'redirect', ['length' => 255])->nullable()->default('')->comment('重定向地址');
            $table->addColumn('string', 'link', ['length' => 255])->nullable()->default('')->comment('外部链接');
            $table->addColumn('string', 'iframe_src', ['length' => 255])->nullable()->default('')->comment('内嵌页面地址');
            $table->addColumn('integer', 'hide_in_menu', [])->nullable()->default(0)->comment('是否隐藏菜单(1隐藏,0显示)');
            $table->addColumn('integer', 'hide_in_breadcrumb', [])->nullable()->default(0)->comment('是否隐藏面包屑(1隐藏,0显示)');
            $table->addColumn('integer', 'hide_in_tab', [])->nullable()->default(0)->comment('是否隐藏标签页(1隐藏,0显示)');
            $table->addColumn('integer', 'keep_alive', [])->nullable()->default(0)->comment('是否缓存组件(1缓存,0不缓存)');
            $table->addColumn('integer', 'affix_tab', [])->nullable()->default(0)->comment('是否固定标签页(1固定,0不固定)');
            $table->addColumn('bigInteger', 'sort', [])->nullable()->default(0)->comment('排序权重');
            $table->addColumn('bigInteger', 'status', [])->nullable()->default(1)->comment('状态(1启用,0禁用)');
            $table->addColumn('string', 'remark', ['length' => 255])->nullable()->default('')->comment('备注');
            $table->addColumn('bigInteger', 'created_by', [])->nullable()->default(0)->comment('创建者');
            $table->addColumn('bigInteger', 'updated_by', [])->nullable()->default(0)->comment('更新者');
            $table->addColumn('timestamp', 'created_at', [])->nullable()->comment('创建时间');
            $table->addColumn('timestamp', 'updated_at', [])->nullable()->comment('更新时间');
            $table->addColumn('timestamp', 'deleted_at', [])->nullable()->comment('删除时间');
            $table->index(['deleted_at'], 'idx_sm_09ec_deleted_at');
            $table->index(['code'], 'idx_sm_09ec_code');
            $table->index(['pid'], 'idx_sm_09ec_pid');
            $table->index(['sort'], 'idx_sm_09ec_sort');
            $table->index(['status'], 'idx_sm_09ec_status');
            $table->comment('系统菜单表');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_menu');
    }
};
