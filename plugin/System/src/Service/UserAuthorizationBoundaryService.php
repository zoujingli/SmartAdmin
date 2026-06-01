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
use Library\Constants\DataField;
use Library\Constants\Status;
use Library\Constants\System;
use Library\Events\Processor\ScopeProcessor;
use Library\Exception\ErrorResponseException;
use Library\Exception\NotAllowResponseException;
use System\Mapper\UserMapper;
use System\Model\SystemRole;
use System\Model\SystemUser;

/**
 * System 用户授权边界校验服务。
 *
 * 只判断“当前操作者能不能改这些用户关系或账号状态”，不执行写库，避免 CRUD 编排和权限边界混在同一个服务中。
 */
final class UserAuthorizationBoundaryService
{
    public function __construct(
        private readonly UserMapper $mapper,
    ) {}

    /**
     * 超级管理员是系统兜底恢复入口，不能被禁用、软删或硬删。
     *
     * @param array<int|string, mixed> $ids
     */
    public function assertSuperAdminProtected(array $ids, string $action): void
    {
        $superId = System::getSuperId();
        foreach ($ids as $id) {
            if (!$this->isSuperAdminId($id, $superId)) {
                continue;
            }

            throw new ErrorResponseException(sprintf('超级管理员账号不允许%s', $action));
        }
    }

    /**
     * 只有平台超级管理员可以配置 SaaS 子超管状态。
     */
    public function assertCanManageTenantSuperStatus(): void
    {
        $currentUser = user(SystemUser::class);
        if (!$currentUser || !$currentUser->isSuper()) {
            throw new NotAllowResponseException('只有超级管理员可以配置子超管');
        }
    }

    /**
     * 非平台超级管理员只能管理本租户普通用户，不能通过禁用、删除、重置密码等动作间接接管子超管账号。
     *
     * @param array<int|string, mixed> $ids
     */
    public function assertTenantSuperUserOperationAllowed(array $ids, string $action): void
    {
        $currentUser = user(SystemUser::class);
        if ($currentUser?->isSuper()) {
            return;
        }

        $currentTenantId = (int)($currentUser?->tenant_id ?? 0);
        foreach ($ids as $id) {
            $user = $this->findUserWithoutTenantScope($id, true);
            if (!$user instanceof SystemUser || !$user->isTenantSuper()) {
                continue;
            }

            if ($currentTenantId > 0 && (int)$user->tenant_id === $currentTenantId) {
                throw new NotAllowResponseException(sprintf('只有超级管理员可以%s子超管账号', $action));
            }
        }
    }

    /**
     * 取消子超管、禁用或删除用户前，保护租户内最后一个可用子超管。
     *
     * @param array<int|string, mixed> $ids
     */
    public function assertTenantSuperProtected(array $ids, string $action): void
    {
        foreach ($ids as $id) {
            $user = $this->findUserWithoutTenantScope($id, true);
            if (!$user instanceof SystemUser || !$user->isTenantSuper() || !Status::isEnabled((int)$user->status) || (string)($user->deleted_at ?? '') !== '') {
                continue;
            }

            if ($this->enabledTenantSuperCount((int)$user->tenant_id, (int)$user->id) <= 0) {
                throw new ErrorResponseException(sprintf('租户最后一个子超管不允许%s', $action));
            }
        }
    }

    /**
     * 替换用户角色时同时校验旧角色和新角色。
     *
     * 旧角色不可授予时不允许被当前操作者覆盖，避免通过“先清空再重绑”间接接管高权限用户。
     *
     * @param array<int|string, mixed> $nextRoleIds
     */
    public function assertCanReplaceUserRoles(int $userId, array $nextRoleIds): void
    {
        $this->assertGrantableRoleIds($this->mapper->getUserRoleIds($userId));
        $this->assertGrantableRoleIds($nextRoleIds);
    }

    /**
     * 用户角色授权必须满足两层边界：角色记录在当前数据范围内，且角色包含的权限节点不能超出当前用户已有权限。
     *
     * @param array<int|string, mixed> $roleIds
     */
    public function assertGrantableRoleIds(array $roleIds): void
    {
        $roleIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $roleId): int => (int)$roleId, $roleIds),
            static fn (int $roleId): bool => $roleId > 0
        )));
        if ($roleIds === []) {
            return;
        }

        $currentUser = user();
        if (!$currentUser) {
            throw new NotAllowResponseException('无权限分配用户角色');
        }

        if ($currentUser->isSuper()) {
            return;
        }

        $query = SystemRole::query()
            ->with('nodes')
            ->whereIn('id', $roleIds);
        if ($currentUser instanceof SystemUser && $currentUser->isSuper()) {
            // 平台超级管理员给目标租户账号分配角色时，需要显式跳出当前租户范围，再用数据范围与授权节点校验兜底。
            $query->withoutGlobalScope(DataField::TENANT);
        }
        ScopeProcessor::applyScope($query, $currentUser, 'created_by');
        $roles = $query->get();
        if ($roles->count() !== count($roleIds)) {
            throw new NotAllowResponseException('存在无权限分配的角色');
        }

        $allowed = array_fill_keys(array_map('strval', $currentUser->getPermissions()), true);
        foreach ($roles as $role) {
            if (!$role instanceof SystemRole) {
                continue;
            }

            $nodes = array_values(array_filter(array_map('strval', $role->getPermissionNodes())));
            if (in_array('*', $nodes, true)) {
                throw new NotAllowResponseException('只有超级管理员可以分配拥有全部权限的角色');
            }

            $denied = array_values(array_filter($nodes, static fn (string $node): bool => !isset($allowed[$node])));
            if ($denied !== []) {
                throw new NotAllowResponseException(sprintf('角色 %s 包含当前用户不可授予的权限', (string)$role->name));
            }
        }
    }

    /**
     * 部门、岗位等用户关联写入前先按目标表数据范围校验。
     *
     * 失败时直接抛错，避免用户基础信息已更新但关联同步阶段被 Mapper 静默拒绝。
     *
     * @param class-string<Model> $modelClass
     * @param array<int|string, mixed> $ids
     */
    public function assertScopedRelationIds(string $modelClass, array $ids, string $label): void
    {
        if ($ids === []) {
            return;
        }

        if (!$this->mapper->scopedRelationIdsExist($modelClass, $ids)) {
            throw new NotAllowResponseException("存在无权限分配的{$label}");
        }
    }

    /**
     * 兼容服务层直接传模型或主键的调用方式，统一识别当前配置的超级管理员 ID。
     */
    private function isSuperAdminId(mixed $id, int $superId): bool
    {
        if ($id instanceof SystemUser) {
            $id = $id->id;
        }

        return is_numeric($id) && (int)$id === $superId;
    }

    private function findUserWithoutTenantScope(mixed $id, bool $withTrashed = false): ?SystemUser
    {
        if ($id instanceof SystemUser) {
            return $id;
        }

        if (!is_numeric($id)) {
            return null;
        }

        $query = SystemUser::query()->withoutGlobalScope(DataField::TENANT);
        if ($withTrashed) {
            $query->withTrashed();
        }

        $user = $query->find((int)$id);

        return $user instanceof SystemUser ? $user : null;
    }

    private function enabledTenantSuperCount(int $tenantId, int $excludeUserId = 0): int
    {
        if ($tenantId <= 0) {
            return 0;
        }

        $query = SystemUser::query()
            ->withoutGlobalScope(DataField::TENANT)
            ->where(DataField::TENANT, $tenantId)
            ->where('super', 1)
            ->where('status', Status::ENABLED)
            ->whereNull('deleted_at');

        if ($excludeUserId > 0) {
            $query->where('id', '!=', $excludeUserId);
        }

        return (int)$query->count();
    }
}
