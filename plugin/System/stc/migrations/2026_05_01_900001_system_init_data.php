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
use System\Service\SystemBootstrapService;

return new class extends Migration {
    public function up(): void
    {
        $report = (new SystemBootstrapService())->syncWithReport(false);
        $menu = is_array($report['menu'] ?? null) ? $report['menu'] : [];
        $auth = is_array($report['auth'] ?? null) ? $report['auth'] : [];

        echo sprintf(
            "Initialized system bootstrap: user %d, menus +%d/~%d, nodes +%d/~%d, wildcard %s\n",
            (int)($report['super_user_id'] ?? 0),
            (int)($menu['added'] ?? 0),
            (int)($menu['updated'] ?? 0),
            (int)($auth['added'] ?? 0),
            (int)($auth['updated'] ?? 0),
            !empty($report['wildcard_binding']) ? 'bound' : 'skipped'
        );
    }

    public function down(): void
    {
        // 基础初始化数据是系统运行基线，回滚不删除，避免误删管理员后续调整过的菜单、账号和系统参数。
    }
};
