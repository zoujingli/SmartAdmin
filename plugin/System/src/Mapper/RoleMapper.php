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
use Library\Constants\Status;
use Library\CoreMapper;
use System\Model\SystemMenu;
use System\Model\SystemRole;

final class RoleMapper extends CoreMapper
{
    /**
     * @param string $model 角色模型类
     */
    public function __construct(
        protected string $model = SystemRole::class
    ) {}

    /**
     * 获取角色详情及用户、权限节点关联。
     */
    public function getRoleWithRelations(int $id): ?SystemRole
    {
        $query = $this->model::with(['users', 'nodes']);

        return $this->applyDataScope($query, 'created_by')->find($id);
    }

    /**
     * 根据用户 ID 获取角色列表。
     */
    public function getRolesByUser(int $userId): array
    {
        $query = $this->model::whereHas('users', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        });

        $query = $this->applyDataScope($query, 'created_by');

        return $query->get()->toArray();
    }

    /**
     * 根据菜单 ID 获取拥有该节点权限的角色列表。
     */
    public function getRolesByMenu(int $menuId): array
    {
        $menuCode = SystemMenu::query()->where('id', $menuId)->value('code');
        if (!$menuCode) {
            return [];
        }

        $query = $this->model::whereHas('nodes', function ($query) use ($menuCode) {
            $query->where('system_node.node', $menuCode);
        });

        $query = $this->applyDataScope($query, 'created_by');

        return $query->get()->toArray();
    }

    /**
     * 获取启用状态的角色选项。
     */
    public function getNormalOptions(array $params = []): array
    {
        $query = $this->model::where('status', Status::ENABLED);
        $query = $this->applyRequestedTenantScope($query, $params);
        $query = $this->applyDataScope($query, 'created_by');

        return $query->orderBy('sort', 'desc')
            ->orderBy('id', 'desc')
            ->get(['id', 'name'])
            ->toArray();
    }

    /**
     * 根据数据范围类型获取角色列表。
     */
    public function getRolesByScope(int $scope): array
    {
        $query = $this->model::where('scope', $scope)
            ->where('status', Status::ENABLED);

        $query = $this->applyDataScope($query, 'created_by');

        return $query->get()->toArray();
    }

    /**
     * 获取角色统计信息。
     */
    public function getStatistics(array $params = []): array
    {
        return $this->buildStatusStatisticsSummary($params);
    }

    /**
     * 角色列表查询条件。
     */
    protected function handleSearch(Builder $query, array $params): Builder
    {
        return _query($query, $params)
            ->like('name,code')
            ->equal('status,scope')
            ->dateBetween('created_at')
            ->getQuery();
    }

    /**
     * 角色列表扩展统计信息。
     */
    protected function handleListExtra(array $params = [], bool $isScope = true): array
    {
        return $this->buildStatusListExtra($params, $isScope);
    }
}
