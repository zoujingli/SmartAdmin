<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://github.com/zoujingli/SmartAdmin/blob/master/readme.md
 */

namespace System\Mapper;

use Hyperf\Database\Model\Builder;
use Library\Constants\MenuType;
use Library\Constants\Status;
use Library\CoreMapper;
use Library\Helper\ArrayTreeHelper;
use System\Model\SystemMenu;
use System\Model\SystemNode;
use System\Model\SystemRole;
use System\Model\SystemUser;
use System\Support\SystemMenuFormatter;
use System\Support\SystemNodeRegistry;

final class MenuMapper extends CoreMapper
{
    /**
     * @param string $model 菜单模型类
     */
    public function __construct(
        protected string $model = SystemMenu::class
    ) {}

    /**
     * 获取菜单及其上下级、权限节点关联。
     */
    public function getMenuWithRelations(int $id): ?SystemMenu
    {
        $query = $this->model::with(['parent', 'children', 'node']);

        return $this->applyDataScope($query, 'created_by')->find($id);
    }

    /**
     * 获取顶级菜单列表。
     */
    public function getTopMenus(): array
    {
        return $this->model::where('pid', 0)
            ->where('status', Status::ENABLED)
            ->orderBy('sort', 'desc')
            ->orderBy('id', 'asc')
            ->get()
            ->toArray();
    }

    /**
     * 按父级 ID 获取子菜单。
     */
    public function getChildrenByPid(int $pid): array
    {
        return $this->model::where('pid', $pid)
            ->where('status', Status::ENABLED)
            ->orderBy('sort', 'desc')
            ->orderBy('id', 'asc')
            ->get()
            ->toArray();
    }

    /**
     * 获取角色可见菜单。
     */
    public function getMenusByRole(int $roleId): array
    {
        $role = SystemRole::query()->find($roleId);
        if (!$role) {
            return [];
        }

        return $this->getMenusByPermissions($role->getPermissionNodes());
    }

    /**
     * 获取用户可见菜单。
     */
    public function getMenusByUser(int $userId): array
    {
        $user = SystemUser::query()->find($userId);
        if (!$user) {
            return [];
        }

        return $this->getMenusByPermissions($user->getPermissions());
    }

    /**
     * 获取菜单树。
     */
    public function getTree(array $conditions = []): array
    {
        $query = $this->model::query();

        foreach ($conditions as $field => $value) {
            $query->where($field, $value);
        }

        $menus = $query->orderBy('sort', 'desc')
            ->orderBy('id', 'asc')
            ->get()
            ->toArray();

        return ArrayTreeHelper::build($menus);
    }

    /**
     * 获取菜单下拉选项。
     */
    public function getOptions(): array
    {
        return $this->model::where('status', Status::ENABLED)
            ->whereIn('type', MenuType::getContainerQueryValues())
            ->orderBy('sort', 'desc')
            ->orderBy('id', 'asc')
            ->get(['id', 'name', 'pid', 'type'])
            ->toArray();
    }

    /**
     * 按菜单类型获取菜单列表。
     */
    public function getMenusByType(string $type): array
    {
        return $this->model::where('type', $type)
            ->where('status', Status::ENABLED)
            ->orderBy('sort', 'desc')
            ->orderBy('id', 'asc')
            ->get()
            ->toArray();
    }

    /**
     * 获取按钮权限列表。
     */
    public function getButtonPermissions(): array
    {
        return $this->model::whereIn('type', MenuType::getQueryValues(MenuType::BUTTON))
            ->where('status', Status::ENABLED)
            ->get(['id', 'name', 'code', 'route'])
            ->toArray();
    }

    /**
     * 获取后台注解注册的权限节点建议。
     *
     * 菜单权限码允许手动输入，因此这里只返回候选项，不参与菜单写入校验。
     *
     * @return array<int, array{value:string,label:string,node:string,name:string}>
     */
    public function getNodeOptions(array $params = []): array
    {
        $keyword = trim((string)($params['keyword'] ?? ''));
        $limit = max(1, min((int)($params['limit'] ?? 50), 200));
        $query = SystemNode::query()
            ->where('source', SystemNodeRegistry::SOURCE_ANNOTATION)
            ->where('status', Status::ENABLED);

        if ($keyword !== '') {
            $query->where(function (Builder $subQuery) use ($keyword): void {
                $like = "%{$keyword}%";
                $subQuery->where('node', 'like', $like)
                    ->orWhere('name', 'like', $like);
            });
        }

        return $query->orderBy('node', 'asc')
            ->limit($limit)
            ->get(['node', 'name'])
            ->map(static function (SystemNode $node): array {
                $code = (string)$node->node;
                $name = (string)$node->name;

                return [
                    'value' => $code,
                    'label' => trim($code . ' ' . $name),
                    'node' => $code,
                    'name' => $name,
                ];
            })
            ->toArray();
    }

    /**
     * 获取菜单统计信息。
     */
    public function getStatistics(array $params = []): array
    {
        return $this->buildStatusStatisticsSummary($params);
    }

    /**
     * 菜单默认主键排序方向（升序）。
     */
    protected function defaultIdOrderDirection(): string
    {
        return 'asc';
    }

    /**
     * sort 相同情况下的主键排序方向（升序）。
     */
    protected function sortTieBreakerDirection(string $sortDirection): string
    {
        return 'asc';
    }

    /**
     * 菜单列表查询条件。
     */
    protected function handleSearch(Builder $query, array $params): Builder
    {
        return _query($query, $params)
            ->like('name,code')
            ->equal('status,type,pid')
            ->dateBetween('created_at')
            ->getQuery();
    }

    /**
     * 菜单列表扩展统计信息。
     */
    protected function handleListExtra(array $params = [], bool $isScope = true): array
    {
        return $this->buildStatusListExtra($params, $isScope);
    }

    /**
     * 列表项格式化。
     */
    protected function handleListItems(array $items, array $params = []): array
    {
        return array_map(function ($item) {
            if (!$item instanceof SystemMenu) {
                return $item;
            }

            return SystemMenuFormatter::decorateModel($item);
        }, $items);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function appendAncestorMenus(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $menuMap = [];
        foreach ($rows as $row) {
            $menuMap[(int)($row['id'] ?? 0)] = $row;
        }

        $pendingParentIds = [];
        foreach ($rows as $row) {
            $parentId = (int)($row['pid'] ?? 0);
            if ($parentId > 0 && !isset($menuMap[$parentId])) {
                $pendingParentIds[$parentId] = $parentId;
            }
        }

        while ($pendingParentIds !== []) {
            $parentIds = array_values($pendingParentIds);
            $pendingParentIds = [];

            $ancestorRows = $this->model::query()
                ->whereIn('id', $parentIds)
                ->where('status', Status::ENABLED)
                ->whereNotIn('type', MenuType::getQueryValues(MenuType::BUTTON))
                ->orderBy('sort', 'desc')
                ->orderBy('id', 'asc')
                ->get()
                ->toArray();

            foreach ($ancestorRows as $row) {
                $id = (int)($row['id'] ?? 0);
                if ($id <= 0 || isset($menuMap[$id])) {
                    continue;
                }

                $menuMap[$id] = $row;

                $parentId = (int)($row['pid'] ?? 0);
                if ($parentId > 0 && !isset($menuMap[$parentId])) {
                    $pendingParentIds[$parentId] = $parentId;
                }
            }
        }

        $menus = array_values($menuMap);
        usort($menus, static function (array $left, array $right): int {
            $sortCompare = ((int)($right['sort'] ?? 0)) <=> ((int)($left['sort'] ?? 0));
            if ($sortCompare !== 0) {
                return $sortCompare;
            }

            return ((int)($left['id'] ?? 0)) <=> ((int)($right['id'] ?? 0));
        });

        return $menus;
    }

    /**
     * 按权限码集合获取可见菜单，并补齐祖先节点用于界面树展示。
     *
     * @param array<int, string> $permissions
     * @return array<int, array<string, mixed>>
     */
    private function getMenusByPermissions(array $permissions): array
    {
        $permissions = array_values(array_unique(array_filter(array_map('strval', $permissions))));
        if ($permissions === []) {
            return [];
        }

        $query = $this->model::query()
            ->where('status', Status::ENABLED)
            ->whereNotIn('type', MenuType::getQueryValues(MenuType::BUTTON))
            ->orderBy('sort', 'desc')
            ->orderBy('id', 'asc');

        if (!in_array('*', $permissions, true)) {
            $query->whereIn('code', $permissions);
        }

        return $this->appendAncestorMenus($query->get()->toArray());
    }
}
