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
use Lcobucci\JWT\Token as JwtToken;
use Library\Auth\Token;
use Library\Constants\DataField;
use Library\Constants\Status;
use Library\CoreModel;
use Library\Exception\ErrorResponseException;
use Library\Interfaces\UserModelInterface;
use Library\Service\LoginService;
use Library\Support\TenantContext;
use System\Model\SystemUser;

/**
 * System 后台登录态服务。
 *
 * 仅恢复和签发 SystemUser Token，不接受 ProjectAccount 等前台用户模型，保证后台 RBAC 与 Project 应用账号隔离。
 */
final class SystemUserSessionService
{
    public function __construct(
        private Token $token,
        private LoginService $authService,
        private OnlineUserService $onlineUsers,
        private TenantService $tenants,
        private UserListSnapshotService $listSnapshots,
    ) {}

    /**
     * 根据 Token 解析当前后台登录用户。
     *
     * System 用户服务只恢复 SystemUser；ProjectAccount 等前台用户必须通过 Library LoginService 显式恢复。
     */
    public function getUser(?string $token = null, ?string $userModel = null): ?UserModelInterface
    {
        try {
            if ($userModel !== null && $userModel !== '' && $userModel !== SystemUser::class) {
                TenantContext::clear();
                return null;
            }

            $token ??= $this->token->getHeaderToken();
            $claims = $this->token->getParserData($token);
            $userId = $claims['uid'] ?? 0;
            if ((string)($claims['class'] ?? '') !== SystemUser::class || !$userId) {
                TenantContext::clear();
                return null;
            }

            $cacheKey = $this->getUserContextCacheKey((int)$userId);
            if (Context::has($cacheKey)) {
                $cached = Context::get($cacheKey);

                return $this->resolveLoginUser(
                    $cached instanceof UserModelInterface ? $cached : null,
                    $token,
                    $cacheKey
                );
            }

            $user = $this->loadSystemUser((int)$userId);

            return $this->resolveLoginUser($user, $token, $cacheKey);
        } catch (\Throwable) {
            TenantContext::clear();
            return null;
        }
    }

    /**
     * 执行 System 后台登录并登记在线会话状态。
     */
    public function login(UserModelInterface $user): JwtToken
    {
        if (!$user instanceof SystemUser) {
            throw new ErrorResponseException('仅支持系统用户登录');
        }
        $this->assertUserTenantAvailable($user);
        $token = $this->authService->login($user);
        $ttl = $this->token->getTTL(get_class($user));
        $this->onlineUsers->markOnline($user, $token->toString(), $ttl > 0 ? $ttl : null);
        $this->listSnapshots->warmDefaultUserListSnapshotAsync((int)$user->getId());

        return $token;
    }

    /**
     * 执行 System 后台登出并标记在线会话离线。
     */
    public function logout(): bool
    {
        $rawToken = $this->token->getHeaderToken();
        $result = $this->authService->logout();
        $this->onlineUsers->markOffline($rawToken);

        return $result;
    }

    /**
     * 判断当前请求是否处于 System 后台已登录状态。
     */
    public function isLogin(): bool
    {
        return $this->getUser() !== null;
    }

    /**
     * 校验当前 System 用户是否拥有指定后台权限节点。
     */
    public function checkAuth(string $node, string $userModel = SystemUser::class): bool
    {
        $user = $this->getUser(null, $userModel);
        if (!$user) {
            return false;
        }

        return $user->hasPermission($node);
    }

    /**
     * 主动踢下指定 System 用户的在线会话，通常用于禁用账号后的即时失效。
     */
    public function markUserOffline(int $userId): void
    {
        $this->onlineUsers->markUserOffline($userId);
    }

    /**
     * 清理用户协程上下文缓存（基础模型 + 关联模型）。
     */
    public function forgetUserContext(int $userId): void
    {
        Context::set($this->getUserContextCacheKey($userId), null);
        Context::set("user_with_relations_{$userId}_0", null);
        Context::set("user_with_relations_{$userId}_1", null);
    }

    /**
     * 生成后台用户协程上下文缓存键。
     */
    private function getUserContextCacheKey(int $userId): string
    {
        return sprintf('system_user_object_%d', $userId);
    }

    /**
     * 加载后台用户模型并携带角色/部门/岗位关联。
     */
    private function loadSystemUser(int $userId): ?UserModelInterface
    {
        /* @var null|UserModelInterface $user */
        return SystemUser::query()
            ->withoutGlobalScope(DataField::TENANT)
            ->with(['roles', 'depts', 'posts'])
            ->find($userId);
    }

    /**
     * 统一处理登录用户对象有效性并回填上下文状态。
     */
    private function resolveLoginUser(?UserModelInterface $user, string $token, string $cacheKey): ?UserModelInterface
    {
        if (!$user instanceof UserModelInterface) {
            Context::set($cacheKey, null);
            TenantContext::clear();

            return null;
        }

        // 登录用户只要暴露 status 字段，都必须在恢复登录态时校验启停状态。
        if ($user instanceof CoreModel && array_key_exists('status', $user->getAttributes()) && !Status::isEnabled((int)$user->status)) {
            Context::set($cacheKey, null);
            TenantContext::clear();
            if ($token !== '') {
                $this->onlineUsers->markOffline($token);
            }

            return null;
        }

        $this->assertUserTenantAvailable($user);
        Context::set($cacheKey, $user);
        $this->applyTenantContext($user);
        if ($token !== '') {
            $this->onlineUsers->touchCurrent($user, $token);
        }

        return $user;
    }

    /**
     * 按用户信息写入租户上下文。
     */
    private function applyTenantContext(UserModelInterface $user): void
    {
        $tenantId = (int)($user->toArray()[DataField::TENANT] ?? TenantContext::PLATFORM_TENANT_ID);
        TenantContext::set($tenantId);
    }

    /**
     * 用户登录和 Token 恢复都必须校验租户状态，避免禁用或过期租户继续访问系统。
     */
    private function assertUserTenantAvailable(UserModelInterface $user): void
    {
        $tenantId = (int)($user->toArray()[DataField::TENANT] ?? TenantContext::PLATFORM_TENANT_ID);
        if ($tenantId <= 0) {
            return;
        }

        $this->tenants->assertTenantAvailable($tenantId);
    }
}
