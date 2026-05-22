<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://github.com/zoujingli/SmartAdmin/blob/master/readme.md
 */

namespace System\Model;

use Carbon\Carbon;
use Hyperf\Database\Model\Collection;
use Hyperf\Database\Model\Relations\BelongsToMany;
use Hyperf\Database\Model\SoftDeletes;
use Library\Constants\DataScope;
use Library\Constants\Status;
use Library\Constants\System;
use Library\CoreModel;
use Library\Exception\ErrorResponseException;

/**
 * @property int $id 主键ID
 * @property int $tenant_id 租户ID
 * @property string $name 角色名称
 * @property string $code 角色编码
 * @property int $scope 数据范围(1全部,2本部门,3部门及下级,4仅本人)
 * @property int $sort 排序权重
 * @property int $status 状态(1启用,0禁用)
 * @property string $remark 备注
 * @property int $created_by 创建者
 * @property int $updated_by 更新者
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 * @property string $deleted_at 删除时间
 * @property-read null|Collection|SystemUser[] $users 
 * @property-read null|Collection|SystemNode[] $nodes 
 * @property-read string $scope_text 
 * @property-read string $status_text 
 */
final class SystemRole extends CoreModel
{
    use SoftDeletes;

    protected array $fillable = ['id', 'tenant_id', 'name', 'code', 'scope', 'sort', 'status', 'remark', 'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at'];

    protected array $logRules = [
        'name' => '系统角色',
        'title' => 'name',
        'fields' => [
            'name' => '角色名称',
            'code' => '角色编码',
            'scope' => [
                'name' => '数据范围',
                'values' => [
                    DataScope::ALL => '全部数据',
                    DataScope::DEPT => '本部门',
                    DataScope::CHILD => '本部门及以下',
                    DataScope::SELF => '仅本人',
                ],
            ],
            'sort' => '排序',
            'status' => ['name' => '状态', 'values' => [Status::DISABLED => '禁用', Status::ENABLED => '启用']],
            'remark' => '备注',
        ],
    ];

    /**
     * 角色与用户的多对多关系。
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(SystemUser::class, 'system_user_role', 'role_id', 'user_id');
    }

    /**
     * 角色与权限节点的多对多关系。
     */
    public function nodes(): BelongsToMany
    {
        return $this->belongsToMany(SystemNode::class, 'system_role_node', 'role_id', 'node_id')
            ->withTimestamps();
    }

    /**
     * 获取角色已分配的权限节点编码列表。
     *
     * @return array<int, string>
     */
    public function getPermissionNodes(): array
    {
        return $this->nodes()
            ->get(['system_node.node'])
            ->pluck('node')
            ->toArray();
    }

    /**
     * 判断角色是否拥有指定权限节点。
     */
    public function hasPermissionNode(string $node): bool
    {
        return $this->nodes()->where('system_node.node', $node)->exists();
    }

    /**
     * 按节点编码同步角色权限。
     *
     * @param array<int, string> $nodes
     */
    public function assignPermissionNodes(array $nodes): void
    {
        $nodes = array_values(array_unique(array_filter(array_map('strval', $nodes))));

        if ($nodes === []) {
            $this->nodes()->sync([]);
            return;
        }

        $allRows = SystemNode::query()
            ->whereIn('node', $nodes)
            ->get(['id', 'node', 'status'])
            ->keyBy('node');

        $missing = [];
        $disabled = [];
        $nodeIds = [];

        foreach ($nodes as $node) {
            $row = $allRows->get($node);
            if (!$row) {
                $missing[] = $node;
                continue;
            }

            if (!Status::isEnabled((int)($row->status ?? Status::DISABLED))) {
                $disabled[] = $node;
                continue;
            }

            $nodeIds[] = (int)$row->id;
        }

        if ($missing !== [] || $disabled !== []) {
            throw new ErrorResponseException('存在无效或已停用的权限节点', [
                'missing_nodes' => array_values(array_unique($missing)),
                'disabled_nodes' => array_values(array_unique($disabled)),
            ]);
        }

        // 角色-节点关联写入不会触发模型事件，租户 ID 必须跟随角色归属，支持平台管理员维护租户角色。
        $this->nodes()->syncWithPivotValues(array_values(array_unique($nodeIds)), ['tenant_id' => (int)($this->tenant_id ?? System::getTenantId())]);
    }

    /**
     * 数据范围文案访问器。
     */
    public function getScopeTextAttribute(): string
    {
        return DataScope::getText($this->scope);
    }

    /**
     * 状态文案访问器。
     */
    public function getStatusTextAttribute(): string
    {
        return Status::getText((int)$this->status);
    }
}
