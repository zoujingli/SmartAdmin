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
use Library\Support\MenuSeedRegistry;

use function Hyperf\Support\env;

/**
 * 将各插件 plugin.json 与旧 Provider 菜单种子同步到 system_menu。
 */
final class MenuSeedSyncService
{
    private const COLUMNS = [
        'id',
        'pid',
        'level',
        'name',
        'code',
        'icon',
        'type',
        'route',
        'component',
        'redirect',
        'link',
        'iframe_src',
        'hide_in_menu',
        'hide_in_breadcrumb',
        'hide_in_tab',
        'keep_alive',
        'affix_tab',
        'sort',
        'status',
        'remark',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * 已从插件清单移除的历史种子菜单。
     *
     * 菜单同步只负责维护项目内置种子，不能扫描删除用户自建菜单；这里用明确 ID + code 白名单软删，
     * 防止旧数据库继续向前端返回已不存在的页面路由。
     */
    private const DEPRECATED_SEED_MENUS = [
        11 => 'dashboard.analytics',
        // Customer 早期使用 11021/project-ownership 作为项目确权目录；当前目录已迁移到 11026/project-rights。
        // 旧目录没有业务子项但会在侧栏形成重复“项目确权”，同步时必须软删。
        11021 => '',
        // License 早期客户管理菜单已下线，保留数据库残留会让前端加载不存在的插件页面组件。
        10010 => 'license.customer.index',
        100101 => 'license.customer.create',
        100102 => 'license.customer.update',
        100103 => 'license.customer.status',
        100104 => 'license.customer.delete',
    ];

    /**
     * 执行菜单种子同步并输出变更报告。
     *
     * @return array{
     *   added:int,
     *   updated:int,
     *   touched:int,
     *   added_menus:array<int, string>,
     *   updated_menus:array<int, string>,
     *   removed:int,
     *   removed_menus:array<int, string>,
     *   skipped:bool
     * }
     */
    public function syncWithReport(bool $dryRun = false): array
    {
        if (!Schema::hasTable('system_menu')) {
            return $this->buildReport(0, 0, 0, 0, [], [], [], true);
        }

        $now = date('Y-m-d H:i:s');
        $actorId = $this->resolveActorId();
        $desired = $this->buildDesiredMenus($actorId, $now);
        $existing = $this->loadExistingMenus(array_keys($desired));
        $deprecated = $this->loadDeprecatedMenus();

        $missing = [];
        $dirty = [];
        foreach ($desired as $id => $row) {
            $exists = $existing[$id] ?? null;
            if ($exists === null) {
                $missing[$id] = $row;
                continue;
            }

            if ($this->isMenuRowDirty($exists, $row)) {
                $dirty[$id] = $row;
            }
        }

        if ($dryRun) {
            return $this->buildReport(
                count($missing),
                count($dirty),
                count($deprecated),
                count($desired),
                $this->formatMenus($missing),
                $this->formatMenus($dirty),
                $this->formatDeprecatedMenus($deprecated),
                false
            );
        }

        Db::beginTransaction();
        try {
            foreach ($missing as $row) {
                Db::table('system_menu')->insert($row);
            }

            foreach ($dirty as $id => $row) {
                $payload = $row;
                unset($payload['id'], $payload['created_at'], $payload['created_by']);
                $payload['deleted_at'] = null;
                $payload['updated_at'] = $now;
                $payload['updated_by'] = $actorId;

                Db::table('system_menu')->where('id', (int)$id)->update($payload);
            }

            foreach ($deprecated as $menu) {
                Db::table('system_menu')
                    ->where('id', (int)$menu->id)
                    ->where('code', (string)$menu->code)
                    ->whereNull('deleted_at')
                    ->update([
                        'deleted_at' => $now,
                        'updated_at' => $now,
                        'updated_by' => $actorId,
                    ]);
            }

            Db::commit();
        } catch (\Throwable $exception) {
            Db::rollBack();
            throw $exception;
        }

        return $this->buildReport(
            count($missing),
            count($dirty),
            count($deprecated),
            count($desired),
            $this->formatMenus($missing),
            $this->formatMenus($dirty),
            $this->formatDeprecatedMenus($deprecated),
            false
        );
    }

    /**
     * 构建期望写入的菜单快照。
     *
     * 应用插件必须由 plugin.json 声明菜单、路由组件和按钮权限，菜单同步只消费这一份结构化清单。
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildDesiredMenus(int $actorId, string $now): array
    {
        $desired = [];
        foreach (MenuSeedRegistry::rows($actorId, $now) as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $row['created_by'] = (int)($row['created_by'] ?? $actorId);
            $row['updated_by'] = (int)($row['updated_by'] ?? $actorId);
            $row['created_at'] = (string)($row['created_at'] ?? $now);
            $row['updated_at'] = (string)($row['updated_at'] ?? $now);
            $row['deleted_at'] = $row['deleted_at'] ?? null;

            $desired[$id] = $this->filterColumns($row);
        }

        return $desired;
    }

    /**
     * 按 ID 批量加载已存在菜单。
     *
     * @param array<int, int> $ids
     * @return array<int, object>
     */
    private function loadExistingMenus(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return Db::table('system_menu')
            ->whereIn('id', $ids)
            ->get(self::COLUMNS)
            ->keyBy('id')
            ->all();
    }

    /**
     * 读取明确标记为已废弃的历史种子菜单。
     *
     * @return array<int, object>
     */
    private function loadDeprecatedMenus(): array
    {
        if (self::DEPRECATED_SEED_MENUS === []) {
            return [];
        }

        return Db::table('system_menu')
            ->whereNull('deleted_at')
            ->where(function ($query): void {
                foreach (self::DEPRECATED_SEED_MENUS as $id => $code) {
                    $query->orWhere(function ($subQuery) use ($id, $code): void {
                        $subQuery->where('id', (int)$id)->where('code', (string)$code);
                    });
                }
            })
            ->get(['id', 'code', 'name'])
            ->all();
    }

    /**
     * 仅保留 system_menu 允许写入的列。
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function filterColumns(array $row): array
    {
        return array_intersect_key($row, array_flip(self::COLUMNS));
    }

    /**
     * 对比数据库记录与期望值是否存在业务差异。
     *
     * @param array<string, mixed> $row
     */
    private function isMenuRowDirty(object $exists, array $row): bool
    {
        foreach ($row as $key => $value) {
            if (in_array($key, ['id', 'created_at', 'created_by', 'updated_at', 'updated_by'], true)) {
                continue;
            }

            if ($this->normalizeValue($exists->{$key} ?? null) !== $this->normalizeValue($value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 统一值比较口径，避免 null/bool/数组类型差异导致误判为“有变更”。
     */
    private function normalizeValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_scalar($value)) {
            return (string)$value;
        }

        $json = json_encode($value, JSON_UNESCAPED_UNICODE);
        return is_string($json) ? $json : '';
    }

    /**
     * 将菜单列表格式化为简要标识集合。
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, string>
     */
    private function formatMenus(array $rows): array
    {
        return array_values(array_map(
            static fn (array $row): string => sprintf('%d:%s', (int)($row['id'] ?? 0), (string)($row['code'] ?? $row['name'] ?? '')),
            $rows
        ));
    }

    /**
     * @param array<int, object> $rows
     * @return array<int, string>
     */
    private function formatDeprecatedMenus(array $rows): array
    {
        return array_values(array_map(
            static fn (object $row): string => sprintf('%d:%s', (int)($row->id ?? 0), (string)($row->code ?? $row->name ?? '')),
            $rows
        ));
    }

    /**
     * 解析同步操作者 ID。
     *
     * Web 场景优先当前登录用户；CLI 场景回退到 `APP_SUPER_USER`。
     */
    private function resolveActorId(): int
    {
        try {
            if (function_exists('user')) {
                $user = user();
                if ($user && method_exists($user, 'getId')) {
                    return (int)$user->getId();
                }
            }
        } catch (\Throwable) {
        }

        return (int)env('APP_SUPER_USER', 1);
    }

    /**
     * 构建同步结果报告。
     *
     * @param array<int, string> $addedMenus
     * @param array<int, string> $updatedMenus
     * @param array<int, string> $removedMenus
     * @return array{
     *   added:int,
     *   updated:int,
     *   removed:int,
     *   touched:int,
     *   added_menus:array<int, string>,
     *   updated_menus:array<int, string>,
     *   removed_menus:array<int, string>,
     *   skipped:bool
     * }
     */
    private function buildReport(int $added, int $updated, int $removed, int $touched, array $addedMenus, array $updatedMenus, array $removedMenus, bool $skipped): array
    {
        return [
            'added' => $added,
            'updated' => $updated,
            'removed' => $removed,
            'touched' => $touched,
            'added_menus' => $addedMenus,
            'updated_menus' => $updatedMenus,
            'removed_menus' => $removedMenus,
            'skipped' => $skipped,
        ];
    }
}
