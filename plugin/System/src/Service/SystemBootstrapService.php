<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace System\Service;

use Hyperf\Database\Schema\Schema;
use Hyperf\DbConnection\Db;
use System\Support\SystemAppMeta;
use System\Support\SystemBootstrapSeed;
use System\Support\SystemNodeRegistry;

use function Hyperf\Support\env;

/**
 * 系统基础数据引导服务。
 *
 * 本地开发迁移、发布包 install 初始化和发布升级兜底共用同一套幂等逻辑，避免正式二进制继续依赖 migrate。
 */
final class SystemBootstrapService
{
    /**
     * 同步超级管理员、菜单、权限节点和系统展示参数。
     *
     * @return array<string, mixed>
     */
    public function syncWithReport(bool $dryRun = false): array
    {
        $superUserId = $this->syncSuperAdmin($dryRun);
        $menu = (new MenuSeedSyncService())->syncWithReport($dryRun);
        $auth = (new AuthRegistryService())->syncWithReport(true, true, $dryRun);
        $wildcardNodeId = $this->ensureWildcardNode($dryRun);
        $wildcardBinding = $this->ensureSuperAdminWildcardBinding($wildcardNodeId, $dryRun);
        $appMeta = $this->syncSystemAppMeta($dryRun);

        return [
            'super_user_id' => $superUserId,
            'menu' => $menu,
            'auth' => $auth,
            'wildcard_node_id' => $wildcardNodeId,
            'wildcard_binding' => $wildcardBinding,
            'app_meta' => $appMeta,
        ];
    }

    private function syncSuperAdmin(bool $dryRun): int
    {
        $configuredUserId = (int)env('APP_SUPER_USER', 1);
        if (!Schema::hasTable('system_user') || !Schema::hasTable('system_role') || !Schema::hasTable('system_user_role')) {
            return $configuredUserId;
        }

        $now = date('Y-m-d H:i:s');
        $superUserId = $this->resolveOrCreateSuperAdminUser($configuredUserId, $now, $dryRun);
        $superRoleId = $this->resolveOrCreateSuperAdminRole($superUserId, $now, $dryRun);

        $exists = Db::table('system_user_role')
            ->where('user_id', $superUserId)
            ->where('role_id', $superRoleId)
            ->exists();
        if (!$exists && !$dryRun) {
            Db::table('system_user_role')->insert(SystemBootstrapSeed::superAdminRoleBindingRow($superUserId));
        }

        return $superUserId;
    }

    private function resolveOrCreateSuperAdminUser(int $configuredUserId, string $now, bool $dryRun): int
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

        if (!$dryRun) {
            Db::table('system_user')->insert(SystemBootstrapSeed::superAdminUserRow($configuredUserId, $now));
        }

        return $configuredUserId;
    }

    private function resolveOrCreateSuperAdminRole(int $superUserId, string $now, bool $dryRun): int
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

        if (!$dryRun) {
            Db::table('system_role')->insert(SystemBootstrapSeed::superAdminRoleRow($superUserId, $now));
        }

        return SystemBootstrapSeed::SUPER_ROLE_ID;
    }

    private function ensureWildcardNode(bool $dryRun): int
    {
        if (!Schema::hasTable('system_node')) {
            return 0;
        }

        $wildcardNode = Db::table('system_node')->where('node', '*')->first();
        if ($wildcardNode) {
            return (int)$wildcardNode->id;
        }

        if ($dryRun) {
            return 0;
        }

        Db::table('system_node')->insert(SystemNodeRegistry::systemRecord('*', date('Y-m-d H:i:s')));

        return (int)(Db::table('system_node')->where('node', '*')->value('id') ?? 0);
    }

    private function ensureSuperAdminWildcardBinding(int $nodeId, bool $dryRun): bool
    {
        if ($nodeId <= 0 || !Schema::hasTable('system_role') || !Schema::hasTable('system_role_node')) {
            return false;
        }

        $roleId = SystemBootstrapSeed::SUPER_ROLE_ID;
        $superRole = Db::table('system_role')
            ->where('id', $roleId)
            ->whereNull('deleted_at')
            ->first();
        if (!$superRole) {
            return false;
        }

        $exists = Db::table('system_role_node')
            ->where('role_id', $roleId)
            ->where('node_id', $nodeId)
            ->exists();
        if ($exists) {
            return true;
        }

        if (!$dryRun) {
            $now = date('Y-m-d H:i:s');
            Db::table('system_role_node')->insert([
                'tenant_id' => 0,
                'role_id' => $roleId,
                'node_id' => $nodeId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return true;
    }

    /**
     * 同步系统显示配置；已有配置只补默认项，不覆盖管理员已保存值。
     *
     * @return array{created:bool,updated:bool,skipped:bool}
     */
    private function syncSystemAppMeta(bool $dryRun): array
    {
        if (!Schema::hasTable('system_data')) {
            return ['created' => false, 'updated' => false, 'skipped' => true];
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
            if ($merged !== $current && !$dryRun) {
                Db::table('system_data')->where('id', $record->id)->update([
                    'value' => $this->encodeValue($merged),
                    'remark' => '系统参数配置',
                    'updated_at' => $now,
                ]);
            }

            return ['created' => false, 'updated' => $merged !== $current, 'skipped' => false];
        }

        if (!$dryRun) {
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
        }

        return ['created' => true, 'updated' => false, 'skipped' => false];
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
}
