<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('system_node') || !Schema::hasColumn('system_node', 'scene')) {
            return;
        }

        Schema::table('system_node', function (Blueprint $table): void {
            // system_node 固定为后台 RBAC 节点表，不再保存动态登录场景或用户模型切换信息。
            $table->dropColumn('scene');
        });
    }

    public function down(): void
    {
        // 清理废弃列属于边界收口，不在回滚时恢复旧动态切换字段。
    }
};
