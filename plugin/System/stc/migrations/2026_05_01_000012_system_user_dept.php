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
        if (Schema::hasTable('system_user_dept')) {
            return;
        }

        Schema::create('system_user_dept', function (Blueprint $table) {
            $table->addColumn('bigInteger', 'user_id', ['unsigned' => true])->comment('用户ID');
            $table->addColumn('bigInteger', 'dept_id', ['unsigned' => true])->comment('部门ID');
            $table->addColumn('bigInteger', 'tenant_id', [])->nullable()->default(0)->comment('租户ID');
            $table->primary(['user_id', 'dept_id']);
            $table->index(['tenant_id'], 'idx_sud_21eb_tenant_id');
            $table->comment('用户与部门关联表');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_user_dept');
    }
};
