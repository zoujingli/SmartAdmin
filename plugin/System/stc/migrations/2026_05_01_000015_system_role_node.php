<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('system_role_node')) {
            return;
        }

        Schema::create('system_role_node', function (Blueprint $table) {
            $table->addColumn('bigInteger', 'id', ['autoIncrement' => true, 'unsigned' => true])->comment('主键ID');
            $table->addColumn('bigInteger', 'role_id', ['unsigned' => true])->comment('角色ID');
            $table->addColumn('bigInteger', 'node_id', ['unsigned' => true])->comment('节点ID');
            $table->addColumn('timestamp', 'created_at', [])->nullable()->comment('创建时间');
            $table->addColumn('timestamp', 'updated_at', [])->nullable()->comment('更新时间');
            $table->addColumn('bigInteger', 'tenant_id', [])->nullable()->default(0)->comment('租户ID');
            $table->index(['node_id'], 'idx_srn_8af5_node_id');
            $table->index(['role_id'], 'idx_srn_8af5_role_id');
            $table->index(['tenant_id'], 'idx_srn_8af5_tenant_id');
            $table->unique(['role_id', 'node_id'], 'uni_srn_8af5_role_id_9ac4d8e7');
            $table->comment('角色与权限节点关联表');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_role_node');
    }
};
