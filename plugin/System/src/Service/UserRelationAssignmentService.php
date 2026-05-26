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

use Hyperf\Database\Model\Model;
use Library\Events\Processor\ScopeProcessor;
use Library\Exception\ErrorResponseException;
use Library\Support\ModelChangeLog;
use System\Mapper\UserMapper;
use System\Model\SystemDept;
use System\Model\SystemPost;
use System\Model\SystemUser;

/**
 * System 用户角色、部门、岗位关系分配服务。
 *
 * 关系表 sync 不触发用户模型字段事件，本服务统一完成边界校验、关系写入、权限缓存清理和手动变更日志。
 */
final class UserRelationAssignmentService
{
    public function __construct(
        private readonly UserMapper $mapper,
        private readonly AuthCacheService $authCache,
        private readonly UserAuthorizationBoundaryService $boundary,
    ) {}

    /**
     * 统一用户关联字段格式，兼容界面使用单值部门字段提交。
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function normalizePayload(array $data): array
    {
        if (array_key_exists('dept_id', $data) && !array_key_exists('dept_ids', $data)) {
            $deptId = (int)($data['dept_id'] ?? 0);
            $data['dept_ids'] = $deptId > 0 ? [$deptId] : [];
        }

        foreach (['role_ids', 'dept_ids', 'post_ids'] as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            $values = is_array($data[$field]) ? $data[$field] : [$data[$field]];
            $data[$field] = array_values(array_filter(array_map(static fn ($value): int => (int)$value, $values)));
        }

        return $data;
    }

    /**
     * 新增用户前校验将要写入的所有关系是否可授予。
     *
     * @param array<string, mixed> $data
     */
    public function assertRelationsForCreate(array $data): void
    {
        $this->boundary->assertGrantableRoleIds($data['role_ids'] ?? []);
        $this->boundary->assertScopedRelationIds(SystemDept::class, $data['dept_ids'] ?? [], '部门');
        $this->boundary->assertScopedRelationIds(SystemPost::class, $data['post_ids'] ?? [], '岗位');
    }

    /**
     * 更新用户前校验角色替换、部门和岗位是否在当前操作边界内。
     *
     * @param array<string, mixed> $data
     */
    public function assertRelationsForUpdate(int $id, array $data): void
    {
        if (array_key_exists('role_ids', $data)) {
            $this->boundary->assertCanReplaceUserRoles($id, $data['role_ids']);
        }
        if (array_key_exists('dept_ids', $data)) {
            $this->boundary->assertScopedRelationIds(SystemDept::class, $data['dept_ids'], '部门');
        }
        if (array_key_exists('post_ids', $data)) {
            $this->boundary->assertScopedRelationIds(SystemPost::class, $data['post_ids'], '岗位');
        }
    }

    /**
     * 新增用户后同步非空关系；空关系保持默认未分配状态。
     *
     * @param array<string, mixed> $data
     */
    public function syncAfterCreate(Model $user, array $data): void
    {
        $id = (int)$user->getAttribute('id');
        if ($id <= 0) {
            throw new ErrorResponseException('用户关系分配失败，用户 ID 无效');
        }

        if (!empty($data['role_ids'])) {
            $oldNames = $this->userRelationNames($id, 'roles');
            $this->assertRelationAssigned($this->mapper->assignRoles($id, $data['role_ids']), '角色');
            $this->recordUserRelationChange($id, 'roles', '角色', $oldNames, $this->userRelationNames($id, 'roles'));
            $this->authCache->forgetUser($id);
        }

        if (!empty($data['dept_ids'])) {
            $oldNames = $this->userRelationNames($id, 'depts');
            $this->assertRelationAssigned($this->mapper->assignDepts($id, $data['dept_ids']), '部门');
            $this->recordUserRelationChange($id, 'depts', '部门', $oldNames, $this->userRelationNames($id, 'depts'));
        }

        if (!empty($data['post_ids'])) {
            $oldNames = $this->userRelationNames($id, 'posts');
            $this->assertRelationAssigned($this->mapper->assignPosts($id, $data['post_ids']), '岗位');
            $this->recordUserRelationChange($id, 'posts', '岗位', $oldNames, $this->userRelationNames($id, 'posts'));
        }
    }

    /**
     * 更新用户后按请求中存在的关系字段执行替换；空数组表示清空该关系。
     *
     * @param array<string, mixed> $data
     */
    public function syncAfterUpdate(int $id, array $data): void
    {
        if (isset($data['role_ids'])) {
            $oldNames = $this->userRelationNames($id, 'roles');
            $this->assertRelationAssigned($this->mapper->assignRoles($id, $data['role_ids']), '角色');
            $this->recordUserRelationChange($id, 'roles', '角色', $oldNames, $this->userRelationNames($id, 'roles'));
            $this->authCache->forgetUser($id);
            ScopeProcessor::clearUserContext($id);
        }

        if (isset($data['dept_ids'])) {
            $oldNames = $this->userRelationNames($id, 'depts');
            $this->assertRelationAssigned($this->mapper->assignDepts($id, $data['dept_ids']), '部门');
            $this->recordUserRelationChange($id, 'depts', '部门', $oldNames, $this->userRelationNames($id, 'depts'));
            ScopeProcessor::clearUserContext($id);
        }

        if (isset($data['post_ids'])) {
            $oldNames = $this->userRelationNames($id, 'posts');
            $this->assertRelationAssigned($this->mapper->assignPosts($id, $data['post_ids']), '岗位');
            $this->recordUserRelationChange($id, 'posts', '岗位', $oldNames, $this->userRelationNames($id, 'posts'));
        }
    }

    /**
     * 分配用户角色。
     *
     * @return array<int, array<string, mixed>>
     */
    public function assignRoles(int $id, array $roleIds): array
    {
        $this->boundary->assertCanReplaceUserRoles($id, $roleIds);
        $oldNames = $this->userRelationNames($id, 'roles');
        if (!$this->mapper->assignRoles($id, $roleIds)) {
            throw new ErrorResponseException('用户不存在或无权限操作');
        }
        $this->recordUserRelationChange($id, 'roles', '角色', $oldNames, $this->userRelationNames($id, 'roles'));
        $this->authCache->forgetUser($id);
        ScopeProcessor::clearUserContext($id);

        return $this->mapper->getUserRoles($id);
    }

    /**
     * 分配用户部门。
     *
     * @return array<int, array<string, mixed>>
     */
    public function assignDepts(int $id, array $deptIds): array
    {
        $this->boundary->assertScopedRelationIds(SystemDept::class, $deptIds, '部门');
        $oldNames = $this->userRelationNames($id, 'depts');
        if (!$this->mapper->assignDepts($id, $deptIds)) {
            throw new ErrorResponseException('用户不存在或无权限操作');
        }
        $this->recordUserRelationChange($id, 'depts', '部门', $oldNames, $this->userRelationNames($id, 'depts'));
        ScopeProcessor::clearUserContext($id);

        return $this->mapper->getUserDepts($id);
    }

    /**
     * 分配用户岗位。
     *
     * @return array<int, array<string, mixed>>
     */
    public function assignPosts(int $id, array $postIds): array
    {
        $this->boundary->assertScopedRelationIds(SystemPost::class, $postIds, '岗位');
        $oldNames = $this->userRelationNames($id, 'posts');
        if (!$this->mapper->assignPosts($id, $postIds)) {
            throw new ErrorResponseException('用户不存在或无权限操作');
        }
        $this->recordUserRelationChange($id, 'posts', '岗位', $oldNames, $this->userRelationNames($id, 'posts'));

        return $this->mapper->getUserPosts($id);
    }

    /**
     * 用户基础资料和组织关系必须整体成功；关联同步失败通常代表目标租户不一致或数据范围不足。
     */
    private function assertRelationAssigned(bool $result, string $label): void
    {
        if (!$result) {
            throw new ErrorResponseException("用户{$label}分配失败，请检查目标租户和数据权限");
        }
    }

    /**
     * 读取用户关系名称快照，用于 Service 层统一记录多对多关系变更。
     *
     * @return array<int, string>
     */
    private function userRelationNames(int $id, string $relation): array
    {
        $rows = match ($relation) {
            'roles' => $this->mapper->getUserRoles($id),
            'depts' => $this->mapper->getUserDepts($id),
            'posts' => $this->mapper->getUserPosts($id),
            default => [],
        };

        return array_values(array_map(
            static fn (array $row): string => (string)($row['name'] ?? ''),
            array_filter($rows, static fn (mixed $row): bool => is_array($row))
        ));
    }

    /**
     * 关系表 sync 不会触发业务模型事件，必须在 Service 编排层显式追加变更分段。
     *
     * @param array<int, string> $oldNames
     * @param array<int, string> $newNames
     */
    private function recordUserRelationChange(int $id, string $field, string $label, array $oldNames, array $newNames): void
    {
        $user = $this->mapper->read($id);
        if (!$user instanceof SystemUser) {
            return;
        }

        ModelChangeLog::recordFields($user, 'updated', [[
            'field' => $field,
            'label' => $label,
            'old' => $oldNames,
            'new' => $newNames,
        ]]);
    }
}
