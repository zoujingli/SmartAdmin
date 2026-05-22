<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Schema;
use Hyperf\DbConnection\Db;
use System\Service\AuthRegistryService;
use System\Service\MenuSeedSyncService;
use System\Support\SystemAppMeta;
use System\Support\SystemBootstrapSeed;
use System\Support\SystemNodeRegistry;

use function Hyperf\Support\env;

return new class extends Migration
{
    public function up(): void
    {
        $this->syncSuperAdmin();
        $this->syncMenus();
        $this->syncNodesAndWildcard();
        $this->syncSystemAppMeta();
    }

    public function down(): void
    {
        // 基础初始化数据是系统运行基线，回滚不删除，避免误删管理员后续调整过的菜单、账号和系统参数。
    }

    private function syncMenus(): void
    {
        if (!Schema::hasTable('system_menu')) {
            return;
        }

        $report = (new MenuSeedSyncService())->syncWithReport(false);
        echo sprintf(
            "Initialized system menus: added %d, updated %d\n",
            $report['added'],
            $report['updated']
        );
    }

    private function syncSuperAdmin(): int
    {
        $configuredUserId = (int)env('APP_SUPER_USER', 1);
        if (!Schema::hasTable('system_user') || !Schema::hasTable('system_role') || !Schema::hasTable('system_user_role')) {
            return $configuredUserId;
        }

        $now = date('Y-m-d H:i:s');
        $superUserId = $this->resolveOrCreateSuperAdminUser($configuredUserId, $now);
        $superRoleId = $this->resolveOrCreateSuperAdminRole($superUserId, $now);

        $exists = Db::table('system_user_role')
            ->where('user_id', $superUserId)
            ->where('role_id', $superRoleId)
            ->exists();
        if (!$exists) {
            Db::table('system_user_role')->insert([
                'tenant_id' => 0,
                'user_id' => $superUserId,
                'role_id' => $superRoleId,
            ]);
            echo "Initialized super admin role binding\n";
        }

        return $superUserId;
    }

    private function syncNodesAndWildcard(): void
    {
        if (!Schema::hasTable('system_menu') || !Schema::hasTable('system_node')) {
            return;
        }

        $report = (new AuthRegistryService())->syncWithReport(true, true, false);
        echo sprintf(
            "Initialized system nodes: added %d, updated %d, disabled %d\n",
            $report['added'],
            $report['updated'],
            $report['disabled']
        );

        $wildcardNodeId = $this->ensureWildcardNode();
        if ($wildcardNodeId > 0) {
            $this->ensureSuperAdminWildcardBinding($wildcardNodeId);
        }
    }

    private function syncSystemAppMeta(): void
    {
        if (!Schema::hasTable('system_data')) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $config = $this->loadConfig();
        $record = Db::table('system_data')
            ->where('name', SystemAppMeta::CONFIG_NAME)
            ->whereNull('deleted_at')
            ->first();
        if ($record) {
            $current = $this->decodeValue($record->value ?? null);
            $merged = SystemAppMeta::mergeDefaults($current, $config);
            if ($merged !== $current) {
                Db::table('system_data')->where('id', $record->id)->update([
                    'value' => $this->encodeValue($merged),
                    'remark' => '系统参数配置',
                    'updated_at' => $now,
                ]);
                echo "Repaired system app meta config\n";
            }

            return;
        }

        Db::table('system_data')->insert([
            'name' => SystemAppMeta::CONFIG_NAME,
            'value' => $this->encodeValue(SystemAppMeta::mergeDefaults([], $config)),
            'remark' => '系统参数配置',
            'created_by' => 0,
            'updated_by' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        echo "Initialized system app meta config\n";
    }

    private function resolveOrCreateSuperAdminUser(int $configuredUserId, string $now): int
    {
        $existingUser = Db::table('system_user')
            ->where('id', $configuredUserId)
            ->whereNull('deleted_at')
            ->first();
        if ($existingUser) {
            return (int)$existingUser->id;
        }

        $existingUsername = Db::table('system_user')
            ->where('username', SystemBootstrapSeed::SUPER_USERNAME)
            ->whereNull('deleted_at')
            ->first();
        if ($existingUsername) {
            return (int)$existingUsername->id;
        }

        Db::table('system_user')->insert(SystemBootstrapSeed::superAdminUserRow($configuredUserId, $now));
        echo 'Initialized super admin user: ' . SystemBootstrapSeed::SUPER_USERNAME . "\n";

        return $configuredUserId;
    }

    private function resolveOrCreateSuperAdminRole(int $superUserId, string $now): int
    {
        $existingRole = Db::table('system_role')
            ->where('id', SystemBootstrapSeed::SUPER_ROLE_ID)
            ->whereNull('deleted_at')
            ->first();
        if (!$existingRole) {
            $existingRole = Db::table('system_role')
                ->where('code', 'super-admin')
                ->whereNull('deleted_at')
                ->first();
        }
        if ($existingRole) {
            return (int)$existingRole->id;
        }

        Db::table('system_role')->insert(SystemBootstrapSeed::superAdminRoleRow($superUserId, $now));
        echo "Initialized super admin role\n";

        return SystemBootstrapSeed::SUPER_ROLE_ID;
    }

    private function ensureWildcardNode(): int
    {
        $wildcardNode = Db::table('system_node')->where('node', '*')->first();
        if ($wildcardNode) {
            return (int)$wildcardNode->id;
        }

        Db::table('system_node')->insert(SystemNodeRegistry::systemRecord('*', date('Y-m-d H:i:s')));

        return (int)(Db::table('system_node')->where('node', '*')->value('id') ?? 0);
    }

    private function ensureSuperAdminWildcardBinding(int $nodeId): void
    {
        if (!Schema::hasTable('system_role') || !Schema::hasTable('system_role_node')) {
            return;
        }

        $roleId = SystemBootstrapSeed::SUPER_ROLE_ID;
        $superRole = Db::table('system_role')
            ->where('id', $roleId)
            ->whereNull('deleted_at')
            ->first();
        if (!$superRole) {
            return;
        }

        $exists = Db::table('system_role_node')
            ->where('role_id', $roleId)
            ->where('node_id', $nodeId)
            ->exists();
        if ($exists) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        Db::table('system_role_node')->insert([
            'tenant_id' => 0,
            'role_id' => $roleId,
            'node_id' => $nodeId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        echo "Bound wildcard node to super admin role\n";
    }

    /**
     * @param array<string, mixed> $value
     */
    private function encodeValue(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeValue(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function loadConfig(): array
    {
        $rows = Db::table('system_data')
            ->where('name', 'like', 'config_%')
            ->whereNull('deleted_at')
            ->get(['name', 'value']);

        $config = [];
        foreach ($rows as $row) {
            $name = str_replace('config_', '', (string)($row->name ?? ''));
            if ($name === '') {
                continue;
            }

            $config[$name] = $this->decodeConfigValue($row->value ?? null);
        }

        return $config;
    }

    private function decodeConfigValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }
};
