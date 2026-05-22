<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://github.com/zoujingli/SmartAdmin/blob/master/readme.md
 */

namespace System\Service;

use Hyperf\Context\Context;
use Psr\SimpleCache\CacheInterface;
use System\Model\SystemRole;

/**
 * 用户鉴权缓存服务（用于 RBAC 鉴权集合缓存与失效）.
 */
final class AuthCacheService
{
    /**
     * @param CacheInterface $cache 鉴权缓存存储（Redis/File 等）
     */
    public function __construct(
        private CacheInterface $cache
    ) {}

    /**
     * 全局鉴权版本缓存键。
     */
    public function getGlobalVersionKey(): string
    {
        return 'system:perm:ver:global';
    }

    /**
     * 单用户鉴权版本缓存键。
     */
    public function getUserVersionKey(int $userId): string
    {
        return "system:perm:ver:user:{$userId}";
    }

    /**
     * 读取全局鉴权版本；未命中时回退为 1。
     */
    public function getGlobalVersion(): int
    {
        $v = $this->cache->get($this->getGlobalVersionKey());
        return is_numeric($v) ? (int)$v : 1;
    }

    /**
     * 读取单用户鉴权版本；未命中时回退为 1。
     */
    public function getUserVersion(int $userId): int
    {
        $v = $this->cache->get($this->getUserVersionKey($userId));
        return is_numeric($v) ? (int)$v : 1;
    }

    /**
     * 提升全局鉴权版本：用于“角色节点变更”等影响全体用户鉴权集合的场景.
     */
    public function bumpGlobalVersion(): int
    {
        $v = $this->nextVersion();
        $this->cache->set($this->getGlobalVersionKey(), $v);
        return $v;
    }

    /**
     * 提升用户鉴权版本：用于“用户角色变更”等只影响单个用户的场景.
     */
    public function bumpUserVersion(int $userId): int
    {
        $v = $this->nextVersion();
        $this->cache->set($this->getUserVersionKey($userId), $v);
        return $v;
    }

    /**
     * 组合用户鉴权缓存键（全局版本 + 用户版本）。
     *
     * 任一版本变化都会使旧键自然失效，避免大规模 delete。
     */
    public function getUserCacheKey(int $userId): string
    {
        $gv = $this->getGlobalVersion();
        $uv = $this->getUserVersion($userId);
        return "system:perm:user:{$userId}:g{$gv}:u{$uv}";
    }

    /**
     * 清理单个用户的鉴权缓存.
     */
    public function forgetUser(int $userId): void
    {
        // 先删“当前版本”缓存，降低 Redis 占用；再 bump 版本让后续请求自然失效
        $this->cache->delete($this->getUserCacheKey($userId));
        $this->bumpUserVersion($userId);

        // 同步清理请求上下文缓存（避免同一请求内继续读到旧鉴权）
        Context::set('system_user_permissions_' . $userId, null);
        Context::set('system_user_permissions_set_' . $userId, null);
    }

    /**
     * 根据角色清理相关用户的鉴权缓存（角色节点变更时调用）.
     */
    public function forgetUsersByRole(int $roleId): int
    {
        // 旧实现会遍历角色下所有用户逐个 delete，成本较高。
        // 这里改为“提升全局版本”让所有用户权限缓存自然失效（旧缓存由 TTL 淘汰）。
        $role = SystemRole::find($roleId);
        if (!$role) {
            return 0;
        }

        $count = (int)$role->users()->count();
        $this->bumpGlobalVersion();
        return $count;
    }

    /**
     * 生成新的鉴权版本号（毫秒级 + 随机扰动，避免并发同值）.
     */
    private function nextVersion(): int
    {
        $ms = (int)floor(microtime(true) * 1000);
        return $ms * 1000 + random_int(0, 999);
    }
}
