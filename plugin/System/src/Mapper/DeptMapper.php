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
use Library\Helper\ArrayTreeHelper;
use System\Model\SystemDept;

final class DeptMapper extends CoreMapper
{
    /**
     * @param string $model 部门模型类
     */
    public function __construct(
        protected string $model = SystemDept::class
    ) {}

    /**
     * 获取部门详情及关联信息。
     */
    public function getDeptWithRelations(int $id): ?array
    {
        $query = $this->model::with(['parent', 'children', 'users']);
        $dept = $this->applyDataScope($query, 'created_by', 'id')->find($id);

        return $dept ? $dept->toArray() : null;
    }

    /**
     * 获取顶级部门列表。
     */
    public function getTopDepts(): array
    {
        return $this->model::where('pid', 0)
            ->orderBy('sort', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * 按父级 ID 获取子部门。
     */
    public function getChildrenByPid(int $pid): array
    {
        return $this->model::where('pid', $pid)
            ->orderBy('sort', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * 获取部门树（含数据范围过滤）。
     */
    public function getTree(array $params = []): array
    {
        // 部门树按部门自身 ID 应用数据范围；只补可见节点的祖先用于展示层级，不扩大可操作范围。
        $query = $this->model::query()
            ->when($params['name'] ?? null, function ($query, $name) {
                return $query->where('name', 'like', "%{$name}%");
            })
            ->when($params['code'] ?? null, function ($query, $code) {
                return $query->where('code', 'like', "%{$code}%");
            })
            ->when(isset($params['status']), function ($query) use ($params) {
                return $query->where('status', $params['status']);
            });
        $query = $this->applyRequestedTenantScope($query, $params);
        $query = $this->applyDataScope($query, 'created_by', 'id');

        $depts = $query->orderBy('sort', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->toArray();

        return $this->buildTree($this->appendAncestorDepts($depts, $params));
    }

    /**
     * 构建树形结构。
     *
     * @param array<int, array<string, mixed>> $depts
     * @return array<int, array<string, mixed>>
     */
    public function buildTree(array $depts, int $pid = 0): array
    {
        return ArrayTreeHelper::build($depts, $pid);
    }

    /**
     * 获取部门下拉选项。
     */
    public function getOptions(array $params = []): array
    {
        $query = $this->model::where('status', Status::ENABLED);
        $query = $this->applyRequestedTenantScope($query, $params);
        $query = $this->applyDataScope($query, 'created_by', 'id');

        return $query->orderBy('sort', 'desc')
            ->orderBy('id', 'desc')
            ->get(['id', 'name', 'pid'])
            ->toArray();
    }

    /**
     * 获取部门下用户列表。
     */
    public function getDeptUsers(int $id): array
    {
        $query = $this->model::with('users');
        $dept = $this->applyDataScope($query, 'created_by', 'id')->find($id);

        return $dept ? $dept->users->toArray() : [];
    }

    /**
     * 判断部门是否存在子部门。
     */
    public function hasChildren(int $id): bool
    {
        return $this->model::where('pid', $id)->exists();
    }

    /**
     * 判断部门是否存在用户。
     */
    public function hasUsers(int $id): bool
    {
        return $this->model::where('id', $id)
            ->whereHas('users')
            ->exists();
    }

    /**
     * 获取部门统计信息。
     */
    public function getStatistics(array $params = []): array
    {
        return $this->buildStatusStatisticsSummary($params);
    }

    /**
     * 部门列表查询条件。
     */
    protected function handleSearch(Builder $query, array $params): Builder
    {
        return _query($query, $params)
            ->like('name,code,leader,phone,email')
            ->equal('status,pid')
            ->dateBetween('created_at')
            ->getQuery();
    }

    /**
     * 部门列表扩展统计信息。
     */
    protected function handleListExtra(array $params = [], bool $isScope = true): array
    {
        return $this->buildStatusListExtra($params, $isScope);
    }

    /**
     * 部门写操作数据范围。
     */
    protected function applyOperationScope(Builder $query): Builder
    {
        // 部门表的“本部门/下级部门”语义依赖部门 ID，标准读写也必须与树和选项保持同一口径。
        return $this->applyDataScope($query, 'created_by', 'id');
    }

    /**
     * 为可见部门补齐祖先节点，确保树路径完整。
     *
     * @param array<int, array<string, mixed>> $depts
     * @return array<int, array<string, mixed>>
     */
    private function appendAncestorDepts(array $depts, array $params = []): array
    {
        if ($depts === []) {
            return [];
        }

        $exists = [];
        $ancestorIds = [];
        foreach ($depts as $dept) {
            $id = (int)($dept['id'] ?? 0);
            if ($id > 0) {
                $exists[$id] = true;
            }

            $segments = array_filter(explode(',', trim((string)($dept['level'] ?? ''), ',')));
            foreach ($segments as $segment) {
                $ancestorId = (int)$segment;
                if ($ancestorId > 0 && !isset($exists[$ancestorId])) {
                    $ancestorIds[$ancestorId] = true;
                }
            }
        }

        if ($ancestorIds === []) {
            return $depts;
        }

        $ancestorQuery = $this->model::whereIn('id', array_keys($ancestorIds));
        $ancestorQuery = $this->applyRequestedTenantScope($ancestorQuery, $params);
        $ancestors = $ancestorQuery->get()->toArray();
        $rows = [];
        foreach (array_merge($ancestors, $depts) as $dept) {
            $id = (int)($dept['id'] ?? 0);
            if ($id > 0) {
                $rows[$id] = $dept;
            }
        }

        usort($rows, static fn (array $a, array $b): int => [(int)($b['sort'] ?? 0), (int)($b['id'] ?? 0)] <=> [(int)($a['sort'] ?? 0), (int)($a['id'] ?? 0)]);

        return array_values($rows);
    }
}
