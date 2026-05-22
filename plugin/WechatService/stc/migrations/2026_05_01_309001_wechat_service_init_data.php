<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Schema;
use Hyperf\DbConnection\Db;
use Library\Support\PluginManifestRegistry;
use System\Service\AuthRegistryService;

use function Hyperf\Support\env;

return new class extends Migration
{
    private const ID_MIN = 3000;

    private const ID_MAX = 30999;

    public function up(): void
    {
        $this->syncPluginMenus();
        $this->syncPluginNodes();
    }

    public function down(): void
    {
        // 插件菜单与权限节点属于运行数据，回滚不删除，避免误删管理员已调整的菜单和授权。
    }

    private function syncPluginMenus(): void
    {
        if (!Schema::hasTable('system_menu')) {
            return;
        }

        $userId = (int)env('APP_SUPER_USER', 1);
        $now = date('Y-m-d H:i:s');
        $rows = array_values(array_filter(
            PluginManifestRegistry::menuRows($userId, $now),
            static fn (array $row): bool => (int)($row['id'] ?? 0) >= self::ID_MIN && (int)($row['id'] ?? 0) <= self::ID_MAX
        ));

        foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $exists = Db::table('system_menu')->where('id', $id)->exists();
            if ($exists) {
                $payload = $row;
                unset($payload['created_at'], $payload['created_by']);
                $payload['deleted_at'] = null;
                $payload['updated_at'] = $now;
                $payload['updated_by'] = $userId;
                Db::table('system_menu')->where('id', $id)->update($payload);
                continue;
            }

            Db::table('system_menu')->insert($row);
        }

        echo 'Initialized wechat service menus: ' . count($rows) . "\n";
    }

    private function syncPluginNodes(): void
    {
        if (!Schema::hasTable('system_node') || !Schema::hasTable('system_menu') || !class_exists(AuthRegistryService::class)) {
            return;
        }

        // 插件权限节点来自菜单编码与 #[Auth] 注解；初始化时整体同步，确保开放平台接口节点立即可用。
        $report = (new AuthRegistryService())->syncWithReport(true, true, false);
        echo sprintf(
            "Initialized wechat service auth nodes: added %d, updated %d, disabled %d\n",
            $report['added'],
            $report['updated'],
            $report['disabled']
        );
    }
};
