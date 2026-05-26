<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace System\Mapper;

use Hyperf\Database\ConnectionInterface;
use Hyperf\Database\Model\Builder;
use Hyperf\Database\Model\Model;
use Hyperf\Database\Query\Builder as QueryBuilder;
use Hyperf\DbConnection\Db;
use Library\Constants\DataField;
use Library\Constants\DataScope;
use Library\Constants\Status;
use Library\Constants\System;
use Library\CoreMapper;
use Library\Events\Processor\ScopeProcessor;
use Library\Interfaces\UserModelInterface;
use System\Model\SystemDept;
use System\Model\SystemPost;
use System\Model\SystemRole;
use System\Model\SystemUser;

final class UserMapper extends CoreMapper
{
    /**
     * @param string $model 用户模型类
     */
    public function __construct(
        protected string $model = SystemUser::class
    ) {}

    /**
     * 加载用户及其角色、部门、岗位关联信息。
     */
    public function getUserWithRelations(int $id, bool $isScope = true): ?SystemUser
    {
        $query = $this->model::query()->select([
            'id', 'tenant_id', 'username', 'nickname', 'phone', 'email',
            'avatar', 'signed', 'status', 'remark', 'extra',
            'login_ip', 'login_time', 'created_at', 'updated_at',
        ]);
        if (System::isPlatformTenant()) {
            // 平台空间是管控面，用户详情需要能读取目标租户用户，再由数据范围限制可操作边界。
            $query->withoutGlobalScope(DataField::TENANT);
        }
        if ($isScope) {
            $query = $this->applyDataScope($query, 'created_by');
        }

        $user = $query->find($id);
        if (!$user instanceof SystemUser) {
            return null;
        }

        $user->setRelation('roles', $this->makeUserRolesQuery($id, $isScope)->get());
        $user->setRelation('depts', $this->makeUserDeptsQuery($id, $isScope)->get());
        $user->setRelation('posts', $this->makeUserPostsQuery($id, $isScope)->get());

        return $user;
    }

    /**
     * 根据部门获取用户列表。
     */
    public function getUsersByDept(int $deptId): array
    {
        $query = $this->model::query()->whereHas('depts', static function (Builder $query) use ($deptId): void {
            $query->where('system_dept.id', $deptId);
        });

        return $this->applyDataScope($query, 'created_by')->get()->toArray();
    }

    /**
     * 根据岗位获取用户列表。
     */
    public function getUsersByPost(int $postId): array
    {
        $query = $this->model::query()->whereHas('posts', static function (Builder $query) use ($postId): void {
            $query->where('system_post.id', $postId);
        });

        return $this->applyDataScope($query, 'created_by')->get()->toArray();
    }

    /**
     * 根据角色获取用户列表。
     */
    public function getUsersByRole(int $roleId): array
    {
        $query = $this->model::query()->whereHas('roles', static function (Builder $query) use ($roleId): void {
            $query->where('system_role.id', $roleId);
        });

        return $this->applyDataScope($query, 'created_by')->get()->toArray();
    }

    /**
     * 获取用户选择器选项（支持关键词和数量限制）。
     */
    public function getUserOptions(array $params = []): array
    {
        $keyword = trim((string)($params['keyword'] ?? ''));
        $limit = max(1, min((int)($params['limit'] ?? 20), 100));
        $query = $this->model::query()
            ->where('status', Status::ENABLED);
        $query = ScopeProcessor::applyScope($query, null, 'created_by');

        if ($keyword !== '') {
            $like = "%{$keyword}%";
            $query->where(function (Builder $builder) use ($like) {
                $builder->where('username', 'like', $like)
                    ->orWhere('nickname', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        }

        return $query->orderBy('id', 'desc')
            ->limit($limit)
            ->get(['id', 'username', 'nickname', 'avatar'])
            ->map(static fn (SystemUser $user): array => [
                'id' => (int)$user->id,
                'username' => (string)$user->username,
                'nickname' => (string)$user->nickname,
                'avatar' => (string)$user->avatar,
                'label' => trim(sprintf('%s%s', (string)$user->nickname, $user->nickname && $user->username ? " ({$user->username})" : (string)$user->username)),
            ])
            ->toArray();
    }

    /**
     * 过滤当前操作者数据范围内可见的用户 ID。
     * `$enabledOnly=false` 用于在线用户等运行态统计，避免禁用状态影响历史会话过滤。
     *
     * @param array<int|string, mixed> $ids
     * @return array<int, int>
     */
    public function filterScopedUserIds(array $ids, bool $enabledOnly = true): array
    {
        $ids = $this->normalizeRelationIds($ids);
        if ($ids === []) {
            return [];
        }

        $query = $this->model::query()->whereIn('id', $ids);
        if ($enabledOnly) {
            $query->where('status', Status::ENABLED);
        }

        $this->applyDataScope($query, 'created_by');

        return $query->pluck('id')
            ->map(static fn (mixed $id): int => (int)$id)
            ->toArray();
    }

    /**
     * 对 Service 暴露统一的关联 ID 可见性校验入口，避免业务层重复拼装不同模型的数据范围字段。
     *
     * @param class-string<Model> $modelClass
     * @param array<int|string, mixed> $ids
     */
    public function scopedRelationIdsExist(string $modelClass, array $ids): bool
    {
        return $this->allScopedRelationIdsExist($modelClass, $this->normalizeRelationIds($ids));
    }

    /**
     * 更新最后登录信息。
     */
    public function updateLastLogin(int $id, string $ip): bool
    {
        return $this->model::where('id', $id)->update([
            'login_ip' => $ip,
            'login_time' => date('Y-m-d H:i:s'),
        ]) > 0;
    }

    /**
     * 修改用户密码。
     */
    public function changePassword(int $id, string $password): bool
    {
        $user = $this->read($id);
        if (!$user instanceof SystemUser) {
            return false;
        }

        return $user->fill(['password' => $password])->save();
    }

    /**
     * 分配用户角色。
     */
    public function assignRoles(int $id, array $roleIds): bool
    {
        $user = $this->read($id);
        if (!$user instanceof SystemUser) {
            return false;
        }

        $roleIds = $this->normalizeRelationIds($roleIds);
        $tenantId = (int)($user->tenant_id ?? System::getTenantId());
        if (!$this->allScopedRelationIdsExist(SystemRole::class, $roleIds) || !$this->allRelationIdsInTenant(SystemRole::class, $roleIds, $tenantId)) {
            return false;
        }

        // belongsToMany()->sync() 直接写关联表，不触发模型 Saving 事件，必须显式写入当前租户 ID。
        $user->roles()->syncWithPivotValues($roleIds, ['tenant_id' => $tenantId]);

        return true;
    }

    /**
     * 分配用户部门。
     */
    public function assignDepts(int $id, array $deptIds): bool
    {
        $user = $this->read($id);
        if (!$user instanceof SystemUser) {
            return false;
        }

        $deptIds = $this->normalizeRelationIds($deptIds);
        $tenantId = (int)($user->tenant_id ?? System::getTenantId());
        if (!$this->allScopedRelationIdsExist(SystemDept::class, $deptIds) || !$this->allRelationIdsInTenant(SystemDept::class, $deptIds, $tenantId)) {
            return false;
        }

        // belongsToMany()->sync() 直接写关联表，不触发模型 Saving 事件，必须显式写入当前租户 ID。
        $user->depts()->syncWithPivotValues($deptIds, ['tenant_id' => $tenantId]);

        return true;
    }

    /**
     * 分配用户岗位。
     */
    public function assignPosts(int $id, array $postIds): bool
    {
        $user = $this->read($id);
        if (!$user instanceof SystemUser) {
            return false;
        }

        $postIds = $this->normalizeRelationIds($postIds);
        $tenantId = (int)($user->tenant_id ?? System::getTenantId());
        if (!$this->allScopedRelationIdsExist(SystemPost::class, $postIds) || !$this->allRelationIdsInTenant(SystemPost::class, $postIds, $tenantId)) {
            return false;
        }

        // belongsToMany()->sync() 直接写关联表，不触发模型 Saving 事件，必须显式写入当前租户 ID。
        $user->posts()->syncWithPivotValues($postIds, ['tenant_id' => $tenantId]);

        return true;
    }

    /**
     * 获取用户角色。
     */
    public function getUserRoles(int $id): array
    {
        $user = $this->read($id);
        if (!$user instanceof SystemUser) {
            return [];
        }

        return $this->makeUserRolesQuery($id)->get()->toArray();
    }

    /**
     * 获取用户角色 ID 列表。
     *
     * @return array<int, int>
     */
    public function getUserRoleIds(int $id): array
    {
        $user = $this->read($id);
        if (!$user instanceof SystemUser) {
            return [];
        }

        return $this->makeUserRolesQuery($id)
            ->pluck('id')
            ->map(static fn (mixed $roleId): int => (int)$roleId)
            ->toArray();
    }

    /**
     * 获取用户部门。
     */
    public function getUserDepts(int $id): array
    {
        $user = $this->read($id);
        if (!$user instanceof SystemUser) {
            return [];
        }

        return $this->makeUserDeptsQuery($id)->get()->toArray();
    }

    /**
     * 获取用户岗位。
     */
    public function getUserPosts(int $id): array
    {
        $user = $this->read($id);
        if (!$user instanceof SystemUser) {
            return [];
        }

        return $this->makeUserPostsQuery($id)->get()->toArray();
    }

    /**
     * 构建用户统计信息。
     */
    public function getStatistics(array $params = []): array
    {
        return $this->buildStatusStatisticsSummary($params);
    }

    /**
     * 用户列表改用原生查询，避免模型链路在协程环境下偶发卡顿。
     */
    public function getPageList(?array $params = null, bool $isScope = true, string $pageName = 'page'): array
    {
        $params ??= [];
        $pageSize = max(1, min(100, (int)($params['pageSize'] ?? $params['page_size'] ?? 15)));
        $currentPage = max(1, (int)($params[$pageName] ?? $params['page'] ?? 1));
        $scopeUser = $this->resolveCurrentScopeUser();

        return $this->runUserListQuery(function (ConnectionInterface $connection) use ($params, $isScope, $pageSize, $currentPage, $scopeUser): array {
            $today = date('Y-m-d');
            $totalSql = $this->makeUserListQuery($connection, $params, $isScope, $scopeUser)
                ->selectRaw('COUNT(*)')
                ->toRawSql();
            $activeSql = $this->makeUserListQuery($connection, $params, $isScope, $scopeUser)
                ->selectRaw(sprintf(
                    'SUM(CASE WHEN status = %d THEN 1 ELSE 0 END)',
                    Status::ENABLED
                ))
                ->toRawSql();
            $inactiveSql = $this->makeUserListQuery($connection, $params, $isScope, $scopeUser)
                ->selectRaw(sprintf(
                    'SUM(CASE WHEN status = %d THEN 1 ELSE 0 END)',
                    Status::DISABLED
                ))
                ->toRawSql();
            $todaySql = $this->makeUserListQuery($connection, $params, $isScope, $scopeUser)
                ->selectRaw(sprintf(
                    "SUM(CASE WHEN DATE(created_at) = '%s' THEN 1 ELSE 0 END)",
                    $today
                ))
                ->toRawSql();

            $rawItems = $this->makeUserListQuery($connection, $params, $isScope, $scopeUser)
                ->selectRaw(
                    sprintf(
                        'system_user.*, (%s) as __total, (%s) as __active_count, (%s) as __inactive_count, (%s) as __today_count',
                        $totalSql,
                        $activeSql,
                        $inactiveSql,
                        $todaySql
                    )
                )
                ->orderBy('id', 'desc')
                ->forPage($currentPage, $pageSize)
                ->get()
                ->all();
            $items = array_map(static function ($item): array {
                $data = (array)$item;
                unset($data['__total'], $data['__active_count'], $data['__inactive_count'], $data['__today_count']);

                return $data;
            }, $rawItems);

            $firstRow = (array)($rawItems[0] ?? []);
            $total = (int)($firstRow['__total'] ?? 0);
            $activeCount = (int)($firstRow['__active_count'] ?? 0);
            $inactiveCount = (int)($firstRow['__inactive_count'] ?? 0);
            $todayCount = (int)($firstRow['__today_count'] ?? 0);

            return [
                'items' => $this->handleListItems($items, $params, $connection),
                'pageInfo' => [
                    'total' => $total,
                    'totalPage' => (int)ceil($total / $pageSize),
                    'currentPage' => $currentPage,
                ],
                'extra' => [
                    'statistics' => [
                        'total' => $total,
                        'today' => $todayCount,
                        'by_status' => [
                            Status::ENABLED => $activeCount,
                            Status::DISABLED => $inactiveCount,
                        ],
                        'active_count' => $activeCount,
                        'inactive_count' => $inactiveCount,
                    ],
                ],
            ];
        });
    }

    /**
     * 列表页避免直接使用多对多预加载，先分页查询用户，再批量补齐关联信息。
     */
    protected function handleListItems(array $items, array $params = [], ?ConnectionInterface $connection = null): array
    {
        if ($items === []) {
            return [];
        }

        $connection ??= Db::connection();
        $userIds = array_values(array_filter(array_map(
            static fn ($item): int => (int)(is_array($item) ? ($item['id'] ?? 0) : ($item->id ?? 0)),
            $items
        )));

        $roleMap = $this->buildRoleMap($connection, $userIds);
        $deptMap = $this->buildDeptMap($connection, $userIds);
        $postMap = $this->buildPostMap($connection, $userIds);

        return array_map(static function ($item) use ($roleMap, $deptMap, $postMap): array {
            $data = $item instanceof SystemUser ? $item->toArray() : (array)$item;
            $userId = (int)($data['id'] ?? 0);
            $roles = $roleMap[$userId] ?? [];
            $depts = $deptMap[$userId] ?? [];
            $posts = $postMap[$userId] ?? [];

            return array_merge($data, [
                'roles' => $roles,
                'depts' => $depts,
                'posts' => $posts,
                'roleIds' => array_column($roles, 'id'),
                'roleNames' => array_column($roles, 'name'),
                'deptId' => $depts[0]['id'] ?? null,
                'deptName' => $depts[0]['name'] ?? '',
                'postIds' => array_column($posts, 'id'),
                'postNames' => array_column($posts, 'name'),
            ]);
        }, $items);
    }

    /**
     * 应用 Mapper 自定义筛选条件。
     */
    protected function handleSearch(Builder $query, array $params): Builder
    {
        return _query($query, $params)
            ->like('username,nickname,phone,email')
            ->equal('status')
            ->dateBetween('created_at,login_time')
            ->getQuery();
    }

    /**
     * 构建列表统计扩展数据。
     */
    protected function handleListExtra(array $params = [], bool $isScope = true): array
    {
        return $this->buildStatusListExtra($params, $isScope);
    }

    /**
     * 平台用户管理需要操作目标租户用户；读写仍应用数据范围，避免平台普通账号越权改全量用户。
     *
     * @param array<int, int> $ids
     */
    protected function makeOperationQuery(array $ids = [], bool $withTrashed = false, bool $isScope = true): Builder
    {
        $query = parent::makeOperationQuery($ids, $withTrashed, $isScope);
        if (System::isPlatformTenant()) {
            $query->withoutGlobalScope(DataField::TENANT);
        }

        return $query;
    }

    /**
     * 将数据权限应用到用户列表原生查询。
     */
    private function applyUserListScope(QueryBuilder $query, bool $isScope, ?UserModelInterface $scopeUser = null): void
    {
        if (!$isScope) {
            return;
        }

        // 列表查询运行在重试闭包中，优先使用入口处解析好的用户，避免协程链路中上下文丢失。
        $user = $scopeUser ?? $this->resolveCurrentScopeUser();
        if (!$user) {
            $query->whereRaw('1 = 0');
            return;
        }

        if ($user->isSuper()) {
            return;
        }

        $scope = ScopeProcessor::getUserScope($user);
        if ($scope === DataScope::ALL) {
            return;
        }

        if ($scope === DataScope::SELF) {
            $query->where('created_by', (int)$user->getId());
            return;
        }

        $userIds = ScopeProcessor::getUserIds($user);
        if ($userIds !== []) {
            $query->whereIn('created_by', $userIds);

            return;
        }

        $query->where('created_by', (int)$user->getId());
    }

    /**
     * 应用用户列表筛选条件。
     *
     * @param array<string, mixed> $params
     */
    private function applyUserListFilters(QueryBuilder $query, array $params): void
    {
        $username = trim((string)($params['username'] ?? ''));
        if ($username !== '') {
            $query->where(function ($builder) use ($username) {
                $keyword = "%{$username}%";
                $builder->where('username', 'like', $keyword)
                    ->orWhere('nickname', 'like', $keyword)
                    ->orWhere('phone', 'like', $keyword)
                    ->orWhere('email', 'like', $keyword);
            });
        }

        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', (int)$params['status']);
        }

        $deptId = $params['deptId'] ?? $params['dept_id'] ?? null;
        if ($deptId !== null && $deptId !== '') {
            $query->whereExists(function ($builder) use ($deptId) {
                $builder->selectRaw('1')
                    ->from('system_user_dept')
                    ->whereColumn('system_user_dept.user_id', 'system_user.id')
                    ->where('system_user_dept.dept_id', (int)$deptId);
            });
        }
    }

    /**
     * @param array<int, int> $userIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function buildRoleMap(ConnectionInterface $connection, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $query = $connection->table('system_role')
            ->select([
                'system_user_role.user_id as user_id',
                'system_role.id',
                'system_role.name',
                'system_role.code',
                'system_role.scope',
                'system_role.status',
            ])
            ->join('system_user_role', 'system_role.id', '=', 'system_user_role.role_id')
            ->whereIn('system_user_role.user_id', $userIds)
            ->whereNull('system_role.deleted_at');
        $this->applyRawDataScope($query, 'system_role.created_by');
        $rows = $query
            ->orderBy('system_role.sort', 'desc')
            ->orderBy('system_role.id', 'desc')
            ->get()
            ->all();

        $map = [];
        foreach ($rows as $row) {
            $userId = (int)($row->user_id ?? 0);
            $roleId = (int)($row->id ?? 0);
            $map[$userId][] = [
                'id' => $roleId,
                'name' => (string)($row->name ?? ''),
                'code' => (string)($row->code ?? ''),
                'scope' => (int)($row->scope ?? 0),
                'status' => (int)($row->status ?? 0),
                'pivot' => [
                    'user_id' => $userId,
                    'role_id' => $roleId,
                ],
            ];
        }

        return $map;
    }

    /**
     * @param array<int, int> $userIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function buildDeptMap(ConnectionInterface $connection, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $query = $connection->table('system_dept')
            ->select([
                'system_user_dept.user_id as user_id',
                'system_dept.id',
                'system_dept.name',
                'system_dept.pid',
                'system_dept.status',
            ])
            ->join('system_user_dept', 'system_dept.id', '=', 'system_user_dept.dept_id')
            ->whereIn('system_user_dept.user_id', $userIds)
            ->whereNull('system_dept.deleted_at');
        $this->applyRawDataScope($query, 'system_dept.created_by', 'system_dept.id');
        $rows = $query
            ->orderBy('system_dept.sort', 'desc')
            ->orderBy('system_dept.id', 'desc')
            ->get()
            ->all();

        $map = [];
        foreach ($rows as $row) {
            $userId = (int)($row->user_id ?? 0);
            $deptId = (int)($row->id ?? 0);
            $map[$userId][] = [
                'id' => $deptId,
                'name' => (string)($row->name ?? ''),
                'pid' => (int)($row->pid ?? 0),
                'status' => (int)($row->status ?? 0),
                'pivot' => [
                    'user_id' => $userId,
                    'dept_id' => $deptId,
                ],
            ];
        }

        return $map;
    }

    /**
     * @param array<int, int> $userIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function buildPostMap(ConnectionInterface $connection, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $query = $connection->table('system_post')
            ->select([
                'system_user_post.user_id as user_id',
                'system_post.id',
                'system_post.name',
                'system_post.code',
                'system_post.status',
            ])
            ->join('system_user_post', 'system_post.id', '=', 'system_user_post.post_id')
            ->whereIn('system_user_post.user_id', $userIds)
            ->whereNull('system_post.deleted_at');
        $this->applyRawDataScope($query, 'system_post.created_by');
        $rows = $query
            ->orderBy('system_post.sort', 'desc')
            ->orderBy('system_post.id', 'desc')
            ->get()
            ->all();

        $map = [];
        foreach ($rows as $row) {
            $userId = (int)($row->user_id ?? 0);
            $postId = (int)($row->id ?? 0);
            $map[$userId][] = [
                'id' => $postId,
                'name' => (string)($row->name ?? ''),
                'code' => (string)($row->code ?? ''),
                'status' => (int)($row->status ?? 0),
                'pivot' => [
                    'user_id' => $userId,
                    'post_id' => $postId,
                ],
            ];
        }

        return $map;
    }

    /**
     * 构建用户角色关联查询。
     */
    private function makeUserRolesQuery(int $userId, bool $isScope = true): Builder
    {
        $query = SystemRole::query()
            ->whereHas('users', static function (Builder $query) use ($userId): void {
                $query->where('system_user.id', $userId);
            })
            ->select([
                'id',
                'name',
                'code',
                'scope',
                'sort',
                'status',
                'remark',
                'created_by',
                'updated_by',
                'created_at',
                'updated_at',
            ])
            ->orderBy('sort', 'desc')
            ->orderBy('id', 'desc');
        if (System::isPlatformTenant()) {
            $query->withoutGlobalScope(DataField::TENANT);
        }

        return $isScope ? $this->applyDataScope($query, 'created_by') : $query;
    }

    /**
     * 构建用户部门关联查询。
     */
    private function makeUserDeptsQuery(int $userId, bool $isScope = true): Builder
    {
        $query = SystemDept::query()
            ->whereHas('users', static function (Builder $query) use ($userId): void {
                $query->where('system_user.id', $userId);
            })
            ->select(['id', 'name', 'pid', 'status', 'created_by'])
            ->orderBy('sort', 'desc')
            ->orderBy('id', 'desc');
        if (System::isPlatformTenant()) {
            $query->withoutGlobalScope(DataField::TENANT);
        }

        return $isScope ? $this->applyDataScope($query, 'created_by', 'id') : $query;
    }

    /**
     * 构建用户岗位关联查询。
     */
    private function makeUserPostsQuery(int $userId, bool $isScope = true): Builder
    {
        $query = SystemPost::query()
            ->whereHas('users', static function (Builder $query) use ($userId): void {
                $query->where('system_user.id', $userId);
            })
            ->select(['id', 'name', 'code', 'status', 'created_by'])
            ->orderBy('sort', 'desc')
            ->orderBy('id', 'desc');
        if (System::isPlatformTenant()) {
            $query->withoutGlobalScope(DataField::TENANT);
        }

        return $isScope ? $this->applyDataScope($query, 'created_by') : $query;
    }

    /**
     * 对原生关联查询应用数据范围约束。
     */
    private function applyRawDataScope(QueryBuilder $query, string $userField, ?string $deptField = null): void
    {
        $user = $this->resolveCurrentScopeUser();
        if (!$user) {
            $query->whereRaw('1 = 0');
            return;
        }

        if ($user->isSuper()) {
            return;
        }

        $scope = ScopeProcessor::getUserScope($user);
        if ($scope === DataScope::ALL) {
            return;
        }

        if ($scope === DataScope::SELF) {
            $query->where($userField, (int)$user->getId());
            return;
        }

        if ($deptField !== null && $deptField !== '') {
            $deptIds = ScopeProcessor::getDeptIds($user);
            if ($deptIds !== []) {
                $query->whereIn($deptField, $deptIds);
                return;
            }
        }

        $userIds = ScopeProcessor::getUserIds($user);
        if ($userIds !== []) {
            $query->whereIn($userField, $userIds);
            return;
        }

        $query->where($userField, (int)$user->getId());
    }

    /**
     * 使用同一数据库连接构建用户列表查询。
     */
    private function makeUserListQuery(ConnectionInterface $connection, array $params, bool $isScope, ?UserModelInterface $scopeUser = null): QueryBuilder
    {
        $query = $connection->table('system_user');
        if (($params['recycle'] ?? false) === true) {
            $query->whereNotNull('deleted_at');
        } else {
            $query->whereNull('deleted_at');
        }

        $this->applyUserTenantScope($query, $scopeUser);
        $this->applyUserListScope($query, $isScope, $scopeUser);
        $this->applyUserListFilters($query, $params);

        return $query;
    }

    /**
     * 原生用户列表不会触发 CoreModel 的租户全局范围，必须在 SQL 层显式补齐租户边界。
     *
     * 平台空间 tenant_id=0 允许看全量；租户空间即使角色数据范围是“全部数据”，也只能读取本租户用户。
     */
    private function applyUserTenantScope(QueryBuilder $query, ?UserModelInterface $scopeUser = null): void
    {
        $tenantId = $scopeUser instanceof UserModelInterface
            ? (int)($scopeUser->toArray()[DataField::TENANT] ?? System::getTenantId())
            : System::getTenantId();

        if ($tenantId > 0) {
            $query->where('system_user.' . DataField::TENANT, $tenantId);
        }
    }

    /**
     * 为用户列表读取增加一次轻量重试，降低远程数据库偶发抖动的影响。
     */
    private function runUserListQuery(callable $callback, int $attempts = 2): array
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; ++$attempt) {
            try {
                return $callback(Db::connection());
            } catch (\Throwable $exception) {
                $lastException = $exception;
                if ($attempt < $attempts) {
                    usleep(150000);
                }
            }
        }

        throw $lastException ?? new \RuntimeException('用户列表查询失败');
    }

    /**
     * 解析当前请求用户（优先上下文 user()，缺失时回退 token claims）。
     *
     * 在协程切换或原生查询链路下，偶发出现 user() 未命中上下文的场景；
     * 这里使用 claims 兜底恢复用户，避免误触发数据范围 fail-closed 导致列表全空。
     */
    private function resolveCurrentScopeUser(): ?UserModelInterface
    {
        $current = user();
        if ($current instanceof UserModelInterface) {
            return $current;
        }

        $claims = auth_claims();
        $userModel = is_string($claims['class'] ?? null) ? (string)$claims['class'] : SystemUser::class;
        $userId = (int)($claims['uid'] ?? $claims['id'] ?? $claims['user_id'] ?? 0);
        if ($userId <= 0) {
            return null;
        }

        if ($userModel !== SystemUser::class) {
            // 用户列表属于 System RBAC 范围，ProjectAccount 等前台 Token 不能按相同 ID 误恢复为后台用户。
            return null;
        }

        // Token 兜底恢复发生在上下文异常路径，需绕过当前 TenantContext，避免租户用户被默认平台空间误过滤。
        $fallbackUser = SystemUser::query()
            ->withoutGlobalScope(DataField::TENANT)
            ->find($userId);

        return $fallbackUser instanceof UserModelInterface ? $fallbackUser : null;
    }

    /**
     * @param array<int|string, mixed> $ids
     * @return array<int, int>
     */
    private function normalizeRelationIds(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map(static fn (mixed $id): int => (int)$id, $ids), static fn (int $id): bool => $id > 0)));
    }

    /**
     * 不同关联表的数据范围字段不完全一致：部门表按自身 `id` 表示部门归属，其它关联表按 `created_by` 过滤。
     * 所有传入 ID 必须全部命中，部分命中也视为越权，避免批量分配时混入不可见数据。
     *
     * @param class-string<Model> $modelClass
     * @param array<int, int> $ids
     */
    private function allScopedRelationIdsExist(string $modelClass, array $ids): bool
    {
        if ($ids === []) {
            return true;
        }

        $query = $modelClass::query()->whereIn('id', $ids);
        if (System::isPlatformTenant()) {
            $query->withoutGlobalScope(DataField::TENANT);
        }
        $deptField = is_a($modelClass, SystemDept::class, true) ? 'id' : null;
        $this->applyDataScope($query, 'created_by', $deptField);

        return (int)$query->count() === count($ids);
    }

    /**
     * 平台管理员给租户用户分配关联数据时，仍必须保证角色/部门/岗位属于目标用户同一租户。
     *
     * @param class-string<Model> $modelClass
     * @param array<int, int> $ids
     */
    private function allRelationIdsInTenant(string $modelClass, array $ids, int $tenantId): bool
    {
        if ($ids === []) {
            return true;
        }

        return (int)$modelClass::query()
            ->withoutGlobalScope(DataField::TENANT)
            ->whereIn('id', $ids)
            ->where(DataField::TENANT, $tenantId)
            ->count() === count($ids);
    }
}
