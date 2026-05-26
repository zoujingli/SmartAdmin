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
        if (Schema::hasTable('system_dict')) {
            return;
        }

        Schema::create('system_dict', function (Blueprint $table) {
            $table->addColumn('bigInteger', 'id', ['autoIncrement' => true, 'unsigned' => true])->comment('主键ID');
            $table->addColumn('bigInteger', 'pid', [])->nullable()->default(0)->comment('父级字典ID，0表示字典分类');
            $table->addColumn('string', 'code', ['length' => 100])->nullable()->default('')->comment('字典编码，分类编码全局唯一，字典项编码在同分类下唯一');
            $table->addColumn('string', 'name', ['length' => 100])->nullable()->default('')->comment('字典名称');
            $table->addColumn('string', 'value', ['length' => 100])->nullable()->default('')->comment('字典值，字典项在同分类下唯一');
            $table->addColumn('text', 'extra', [])->nullable()->comment('扩展配置JSON');
            $table->addColumn('bigInteger', 'sort', [])->nullable()->default(0)->comment('排序权重');
            $table->addColumn('bigInteger', 'status', [])->nullable()->default(1)->comment('状态(1启用,0禁用)');
            $table->addColumn('string', 'remark', ['length' => 255])->nullable()->default('')->comment('备注');
            $table->addColumn('bigInteger', 'created_by', [])->nullable()->default(0)->comment('创建者');
            $table->addColumn('bigInteger', 'updated_by', [])->nullable()->default(0)->comment('更新者');
            $table->addColumn('timestamp', 'created_at', [])->nullable()->comment('创建时间');
            $table->addColumn('timestamp', 'updated_at', [])->nullable()->comment('更新时间');
            $table->addColumn('timestamp', 'deleted_at', [])->nullable()->comment('删除时间');
            $table->unique(['pid', 'code'], 'uni_sdict_pid_code');
            $table->index(['deleted_at'], 'idx_sdict_deleted_at');
            $table->index(['pid'], 'idx_sdict_pid');
            $table->index(['sort'], 'idx_sdict_sort');
            $table->index(['status'], 'idx_sdict_status');
            $table->index(['value'], 'idx_sdict_value');
            $table->comment('系统数据字典表');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_dict');
    }
};
