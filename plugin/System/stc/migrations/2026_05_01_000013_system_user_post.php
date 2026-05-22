<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('system_user_post')) {
            return;
        }

        Schema::create('system_user_post', function (Blueprint $table) {
            $table->addColumn('bigInteger', 'user_id', ['unsigned' => true])->comment('用户ID');
            $table->addColumn('bigInteger', 'post_id', ['unsigned' => true])->comment('岗位ID');
            $table->addColumn('bigInteger', 'tenant_id', [])->nullable()->default(0)->comment('租户ID');
            $table->primary(['user_id', 'post_id']);
            $table->index(['tenant_id'], 'idx_sup_f6d5_tenant_id');
            $table->comment('用户与岗位关联表');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_user_post');
    }
};
