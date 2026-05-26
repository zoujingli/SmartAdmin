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

use Hyperf\Context\Context;
use Hyperf\Database\Model\Model;
use Hyperf\DbConnection\Db;
use Lcobucci\JWT\Token as JwtToken;
use Library\Constants\DataField;
use Library\Constants\Status;
use Library\Constants\System;
use Library\CoreService;
use Library\Exception\ErrorResponseException;
use Library\Interfaces\UserModelInterface;
use Library\Support\TenantContext;
use System\Mapper\UserMapper;
use System\Model\SystemUser;

final class UserService extends CoreService
{
    /**
     * 注入用户 CRUD、认证门面、偏好过滤、列表快照与授权缓存等协作服务。
     */
    public function __construct(
        protected UserMapper $mapper,
        protected AuthCacheService $authCache,
        protected TenantService $tenants,
        protected SystemUserSessionService $sessions,
        protected UserPreferenceService $preferences,
        protected UserListSnapshotService $listSnapshots,
        protected UserAccessCodeService $accessCodes,
        protected UserAuthorizationBoundaryService $boundary,
        protected UserRelationAssignmentService $relations,
        protected UserPasswordCredentialService $passwords,
    ) {}

    /**
     * 根据 Token 解析当前后台登录用户。
     */
    public function getUser(?string $token = null, ?string $userModel = null): ?UserModelInterface
    {
        return $this->sessions->getUser($token, $userModel);
    }

    /**
     * 执行登录并登记在线会话状态。
     */
    public function login(UserModelInterface $user): JwtToken
    {
        return $this->sessions->login($user);
    }

    /**
     * 执行登出并标记在线会话离线。
     */
    public function logout(): bool
    {
        return $this->sessions->logout();
    }

    /**
     * 判断当前请求是否处于已登录状态。
     */
    public function isLogin(): bool
    {
        return $this->sessions->isLogin();
    }

    /**
     * 校验当前用户是否拥有指定权限节点。
     */
    public function checkAuth(string $node, string $userModel = SystemUser::class): bool
    {
        return $this->sessions->checkAuth($node, $userModel);
    }

    /**
     * 按用户名查询用户模型。
     */
    public function getUserByUsername(string $username): ?SystemUser
    {
        /* @var null|SystemUser $user */
        return SystemUser::query()
            ->withoutGlobalScope(DataField::TENANT)
            ->where('username', $username)
            ->first();
    }

    /**
     * 获取包含角色、部门、岗位等关联信息的用户数据。
     *
     * 结果会写入协程上下文，避免同请求内重复查库。
     */
    public function getUserWithRelations(int $userId, bool $isScope = true): array
    {
        $cacheKey = sprintf('user_with_relations_%d_%d', $userId, $isScope ? 1 : 0);
        $cached = Context::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        /** @phpstan-ignore-next-line */
        $user = $this->mapper->getUserWithRelations($userId, $isScope);
        if (!$user) {
            throw new ErrorResponseException('用户不存在');
        }

        $userData = $user->toArray();
        Context::set($cacheKey, $userData);

        return $userData;
    }

    /**
     * 当前用户更新自己的基础资料（不可改用户名、状态、角色等）。
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function updateSelfProfile(int $userId, array $input): array
    {
        $allowed = ['nickname', 'email', 'phone', 'avatar', 'signed'];
        $data = [];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $input)) {
                continue;
            }
            $value = $input[$key];
            $data[$key] = is_string($value) ? trim($value) : (string)$value;
        }
        if ($data === []) {
            throw new ErrorResponseException('没有可更新的资料');
        }

        $user = $this->mapper->read($userId, ['*'], false);
        if (!$user instanceof SystemUser) {
            throw new ErrorResponseException('用户不存在');
        }

        $user->update($data);
        $this->forgetUserContextCache($userId);
        $this->listSnapshots->flush();

        return $this->getUserWithRelations($userId, false);
    }

    /**
     * 当前用户保存自己的界面偏好配置。
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function updateSelfPreferences(int $userId, array $input): array
    {
        if (!array_key_exists('ui_preferences', $input)) {
            throw new ErrorResponseException('界面配置格式无效');
        }

        $preferences = $this->preferences->normalizeUiPreferencesInput($input['ui_preferences']);

        $user = $this->mapper->read($userId, ['*'], false);
        if (!$user instanceof SystemUser) {
            throw new ErrorResponseException('用户不存在');
        }

        $extra = $this->preferences->mergeUserExtraUiPreferences($user->extra, $preferences);

        $user->update(['extra' => $extra]);
        $this->forgetUserContextCache($userId);
        $this->listSnapshots->flush();

        return $this->getUserWithRelations($userId, false);
    }

    /**
     * 当前用户修改登录密码（需校验原密码）。
     */
    public function changeOwnPassword(int $userId, string $oldPassword, string $newPassword): void
    {
        $this->passwords->changeOwnPassword($userId, $oldPassword, $newPassword);
        $this->forgetUserContextCache($userId);
        $this->listSnapshots->flush();
    }

    /**
     * 更新用户最后登录信息。
     */
    public function updateLastLogin(int $userId, string $ip): bool
    {
        /* @phpstan-ignore-next-line */
        return $this->mapper->updateLastLogin($userId, $ip);
    }

    /**
     * 创建用户并同步角色/部门/岗位关系。
     */
    public function create(array $data): ?Model
    {
        $data = $this->relations->normalizePayload($data);
        $this->passwords->assertCreatePassword($data);
        $this->relations->assertRelationsForCreate($data);

        Db::beginTransaction();
        try {
            $user = parent::create($data);
            if (!$user instanceof Model) {
                Db::rollBack();
                return null;
            }

            $this->relations->syncAfterCreate($user, $data);

            Db::commit();
        } catch (\Throwable $exception) {
            Db::rollBack();
            throw $exception;
        }

        $this->listSnapshots->flush();

        return $user;
    }

    /**
     * 更新用户基础信息及其角色/部门/岗位关系。
     */
    public function update(mixed $id, array $data): bool
    {
        $data = $this->relations->normalizePayload($data);
        if (array_key_exists('status', $data) && Status::isDisabled((int)$data['status'])) {
            $this->boundary->assertSuperAdminProtected([$id], '禁用');
        }
        $data = $this->passwords->normalizeUpdatePassword($data);
        $this->relations->assertRelationsForUpdate((int)$id, $data);

        Db::beginTransaction();
        try {
            $result = parent::update($id, $data);
            if (!$result) {
                Db::rollBack();
                return false;
            }

            $this->relations->syncAfterUpdate((int)$id, $data);

            Db::commit();
        } catch (\Throwable $exception) {
            Db::rollBack();
            throw $exception;
        }

        $this->listSnapshots->flush();

        return true;
    }

    /**
     * 软删除用户并刷新用户列表快照。
     */
    public function delete(array|int $ids): bool
    {
        $idArray = str2arr($ids);
        $this->boundary->assertSuperAdminProtected($idArray, '删除');
        $result = $this->mapper->delete($idArray);
        $this->listSnapshots->flush();

        return $result;
    }

    /**
     * 恢复用户并清理对应上下文缓存。
     */
    public function recovery(array|int $ids): bool
    {
        $idArray = str2arr($ids);
        $result = $this->mapper->recovery($idArray);
        foreach ($idArray as $id) {
            $this->forgetUserContextCache((int)$id);
        }
        $this->listSnapshots->flush();

        return $result;
    }

    /**
     * 彻底删除用户并清理对应上下文缓存。
     */
    public function delreal(array|int $ids): bool
    {
        $idArray = str2arr($ids);
        $this->boundary->assertSuperAdminProtected($idArray, '彻底删除');
        foreach ($idArray as $id) {
            $this->forgetUserContextCache((int)$id);
        }

        $result = $this->mapper->delreal($idArray);
        $this->listSnapshots->flush();

        return $result;
    }

    /**
     * 管理员重置指定用户密码。
     */
    public function changePassword(int $id, string $password): array
    {
        $result = $this->passwords->resetPassword($id, $password);
        $this->listSnapshots->flush();

        return $result;
    }

    /**
     * 分配用户角色。
     */
    public function assignRoles(int $id, array $roleIds): array
    {
        $roles = $this->relations->assignRoles($id, $roleIds);
        $this->listSnapshots->flush();

        return $roles;
    }

    /**
     * 分配用户部门。
     */
    public function assignDepts(int $id, array $deptIds): array
    {
        $depts = $this->relations->assignDepts($id, $deptIds);
        $this->listSnapshots->flush();

        return $depts;
    }

    /**
     * 分配用户岗位。
     */
    public function assignPosts(int $id, array $postIds): array
    {
        $posts = $this->relations->assignPosts($id, $postIds);
        $this->listSnapshots->flush();

        return $posts;
    }

    /**
     * 获取用户角色列表。
     */
    public function getUserRoles(int $id): array
    {
        return $this->mapper->getUserRoles($id);
    }

    /**
     * 获取用户部门列表。
     */
    public function getUserDepts(int $id): array
    {
        return $this->mapper->getUserDepts($id);
    }

    /**
     * 获取用户岗位列表。
     */
    public function getUserPosts(int $id): array
    {
        return $this->mapper->getUserPosts($id);
    }

    /**
     * 获取用户统计信息。
     */
    public function getStatistics(array $params = []): array
    {
        return $this->mapper->getStatistics($params);
    }

    /**
     * 获取用户下拉选项。
     */
    public function getOptions(array $params = []): array
    {
        return $this->mapper->getUserOptions($params);
    }

    /**
     * 获取用户分页列表（支持快照缓存与降级兜底）。
     */
    public function getPageList(?array $params = null, bool $isScope = true, string $pageName = 'page'): array
    {
        $currentUserId = (int)($this->getUser()?->getId() ?? 0);

        return $this->listSnapshots->getPageList($params, $isScope, $pageName, $currentUserId);
    }

    /**
     * 变更用户状态。
     *
     * 禁用用户时会主动踢下线，防止已登录会话继续访问。
     */
    public function changeStatus(int $id, mixed $status): bool
    {
        if (is_numeric($status) && (int)$status === Status::DISABLED) {
            $this->boundary->assertSuperAdminProtected([$id], '禁用');
        }

        $status = (int)_vali([
            'status.value' => $status,
            'status.integer' => '状态值必须为数字',
            'status.in:1,0' => '状态值错误',
        ])['status'];

        $result = $this->mapper->changeStatus($id, $status);
        $this->forgetUserContextCache($id);
        $this->authCache->forgetUser($id);
        if ($result && Status::isDisabled($status)) {
            $this->sessions->markUserOffline($id);
        }
        $this->listSnapshots->flush();

        return $result;
    }

    /**
     * 变更用户排序值并刷新列表快照。
     */
    public function changeSort(int $id, mixed $sort): bool
    {
        $sort = (int)_vali([
            'sort.value' => $sort,
            'sort.integer' => '排序值必须为数字',
        ])['sort'];
        $result = $this->mapper->changeSort($id, $sort);
        $this->listSnapshots->flush();

        return $result;
    }

    /**
     * 仅提升指定用户的列表快照版本，使其相关缓存失效。
     */
    public function clearUserListSnapshotsForUser(int $userId): void
    {
        $this->listSnapshots->clearUserListSnapshotsForUser($userId);
    }

    /**
     * 清理全局用户列表快照（通过全局版本号提升实现）。
     */
    public function clearAllUserListSnapshots(): void
    {
        $this->listSnapshots->flush();
    }

    /**
     * 获取用户可访问权限码集合。
     */
    public function getUserAccessCodes(int $userId): array
    {
        return $this->accessCodes->getUserAccessCodes($userId);
    }

    /**
     * 用户数据入库前过滤与唯一性校验。
     */
    protected function filterData(array &$data, array $exists = []): array
    {
        foreach (['username', 'nickname', 'phone', 'email', 'avatar', 'signed', 'remark'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        $rules = [
            'tenant_id.integer' => '租户 ID 必须为数字',
            'tenant_id.min:0' => '租户 ID 不能小于 0',
            'username.filled' => '用户名不能为空',
            'username.max:20' => '用户名最多 20 位',
            'nickname.max:30' => '用户昵称最多 30 位',
            'phone.max:11' => '手机号最多 11 位',
            'email.max:50' => '邮箱最多 50 位',
            'password.max:255' => '密码格式错误',
            'avatar.max:255' => '头像地址最多 255 位',
            'signed.max:255' => '个性签名最多 255 位',
            'status.integer' => '状态值必须为数字',
            'status.in:1,0' => '状态值错误',
            'remark.max:255' => '备注最多 255 位',
            'extra.array' => '扩展数据格式错误',
        ];
        if ($exists === []) {
            $rules['username.required'] = '用户名不能为空';
            $rules['status.default'] = Status::ENABLED;
        }

        $data = _vali($rules, $data);
        if (array_key_exists('status', $data)) {
            $data['status'] = (int)$data['status'];
        }

        if (array_key_exists('username', $data) && $data['username'] !== null && $data['username'] !== '') {
            $query = SystemUser::query()
                ->withoutGlobalScope(DataField::TENANT)
                ->where('username', $data['username']);

            if ($exists !== [] && array_key_exists('id', $exists)) {
                $query->where('id', '!=', $exists['id']);
            }

            if ($query->exists()) {
                throw new ErrorResponseException('用户名已存在');
            }
        }

        if (System::isPlatformTenant()) {
            $tenantId = (int)($data['tenant_id'] ?? $exists['tenant_id'] ?? TenantContext::PLATFORM_TENANT_ID);
            $this->tenants->assertTenantAvailable($tenantId);
            $data['tenant_id'] = $tenantId;
        } else {
            // 租户空间内禁止通过请求体切换用户归属，最终租户 ID 由 ModelSavingListener 从当前登录态写入。
            unset($data['tenant_id']);
        }

        return $data;
    }

    /**
     * 清理用户协程上下文缓存（基础模型 + 关联模型）。
     */
    private function forgetUserContextCache(int $userId): void
    {
        $this->sessions->forgetUserContext($userId);
    }
}
