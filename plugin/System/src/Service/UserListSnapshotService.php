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

use Library\Constants\Status;
use Psr\SimpleCache\CacheInterface;
use System\Mapper\UserMapper;
use System\Model\SystemUser;

/**
 * System 用户列表快照缓存服务。
 *
 * 只缓存短期列表响应和版本号，不保存请求对象；查询仍在当前协程执行，避免数据范围上下文丢失后 fail-closed。
 */
final class UserListSnapshotService
{
    public function __construct(
        private readonly UserMapper $mapper,
        private readonly CacheInterface $cache,
    ) {}

    /**
     * 获取用户分页列表（支持快照缓存与降级兜底）。
     */
    public function getPageList(?array $params = null, bool $isScope = true, string $pageName = 'page', int $currentUserId = 0): array
    {
        $params ??= [];
        $currentUserId = $this->normalizeCurrentUserId($currentUserId);
        $cacheKey = $this->getUserListSnapshotKey($params, $isScope, $pageName, $currentUserId);
        // 这里不直接返回缓存，优先实时查询一次，避免历史异常快照长期把列表“锁死”为全空。
        $cached = $this->loadUserListSnapshot($cacheKey);

        $result = $this->runUserListSnapshotQuery($params, $isScope, $pageName);
        if ($result !== null) {
            $this->storeUserListSnapshot($cacheKey, $result);

            if ($this->isDefaultUserListParams($params, $pageName)) {
                $this->storeUserListSnapshot($this->getDefaultUserListSnapshotKey($currentUserId), $result);
            }

            return $result;
        }

        if ($cached = $this->loadUserListSnapshot($cacheKey)) {
            return $cached;
        }

        if ($this->isDefaultUserListParams($params, $pageName)) {
            if ($cached = $this->loadUserListSnapshot($this->getDefaultUserListSnapshotKey($currentUserId))) {
                return $cached;
            }
        }

        return [
            'items' => [],
            'pageInfo' => [
                'total' => 0,
                'totalPage' => 0,
                'currentPage' => max(1, (int)($params[$pageName] ?? $params['page'] ?? 1)),
            ],
            'extra' => [
                'statistics' => [
                    'total' => 0,
                    'today' => 0,
                    'by_status' => [
                        Status::ENABLED => 0,
                        Status::DISABLED => 0,
                    ],
                    'active_count' => 0,
                    'inactive_count' => 0,
                ],
                'stale' => true,
            ],
        ];
    }

    /**
     * 登录成功后的默认用户列表预热入口。
     *
     * 用户列表查询强依赖当前请求的登录用户、Token claims、租户上下文和数据范围。
     * 登录完成后再开新协程无法可靠继承这些上下文，容易触发 fail-closed 并把空列表写入默认快照。
     * 因此这里保留入口但不再主动预热，实际用户列表接口会在有完整请求上下文时实时查询并写入短期快照。
     */
    public function warmDefaultUserListSnapshotAsync(int $userId): void
    {
        // 空操作：避免登录链路在无完整上下文的协程中生成错误快照。
    }

    /**
     * 仅提升指定用户的列表快照版本，使其相关缓存失效。
     */
    public function clearUserListSnapshotsForUser(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $this->cache->set($this->getUserListUserVersionKey($userId), $this->nextSnapshotVersion(), 86400);
    }

    /**
     * 清理全局用户列表快照（通过全局版本号提升实现）。
     */
    public function flush(): void
    {
        $this->cache->set($this->getUserListVersionKey(), $this->nextSnapshotVersion(), 86400);
    }

    /**
     * 执行用户列表查询。
     *
     * 用户列表查询依赖登录上下文（用户、Token、租户），不能放到新协程执行，
     * 否则会因上下文丢失触发数据范围 fail-closed，表现为列表 SQL 带 `1 = 0`。
     */
    private function runUserListSnapshotQuery(array $params, bool $isScope, string $pageName): ?array
    {
        try {
            $result = $this->mapper->getPageList($params, $isScope, $pageName);
            return is_array($result) ? $result : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * 生成用户列表快照缓存键（全局版本 + 用户版本 + 查询参数哈希）。
     */
    private function getUserListSnapshotKey(array $params, bool $isScope, string $pageName, int $userId): string
    {
        $globalVersion = $this->getUserListVersion();
        $userVersion = $this->getUserListUserVersion($userId);
        $normalized = $this->normalizeUserListParams($params, $pageName);
        $payload = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        $hash = md5($payload);

        return sprintf('system:user:list:g%s:u%s:uid%s:s%s:%s', $globalVersion, $userVersion, $userId, $isScope ? 1 : 0, $hash);
    }

    /**
     * 获取默认用户列表（第一页）快照键。
     */
    private function getDefaultUserListSnapshotKey(int $userId): string
    {
        return $this->getUserListSnapshotKey([], true, 'page', $userId);
    }

    /**
     * 读取用户列表快照缓存。
     */
    private function loadUserListSnapshot(string $cacheKey): ?array
    {
        $cached = $this->cache->get($cacheKey);

        return is_array($cached) ? $cached : null;
    }

    /**
     * 写入用户列表快照缓存（短 TTL）。
     */
    private function storeUserListSnapshot(string $cacheKey, array $data): void
    {
        $this->cache->set($cacheKey, $data, 30);
    }

    /**
     * 全局用户列表快照版本键。
     */
    private function getUserListVersionKey(): string
    {
        return 'system:user:list:version';
    }

    /**
     * 读取全局用户列表快照版本。
     */
    private function getUserListVersion(): int
    {
        $version = $this->cache->get($this->getUserListVersionKey());

        return is_numeric($version) ? (int)$version : 1;
    }

    /**
     * 指定用户的用户列表快照版本键。
     */
    private function getUserListUserVersionKey(int $userId): string
    {
        return sprintf('system:user:list:version:user:%d', $userId);
    }

    /**
     * 读取指定用户的用户列表快照版本。
     */
    private function getUserListUserVersion(int $userId): int
    {
        if ($userId <= 0) {
            return 1;
        }

        $version = $this->cache->get($this->getUserListUserVersionKey($userId));

        return is_numeric($version) ? (int)$version : 1;
    }

    /**
     * 生成新的快照版本号（毫秒级 + 随机扰动）。
     */
    private function nextSnapshotVersion(): int
    {
        $ms = (int)floor(microtime(true) * 1000);

        return $ms * 1000 + random_int(0, 999);
    }

    /**
     * 标准化用户列表参数，用于稳定生成缓存键。
     */
    private function normalizeUserListParams(array $params, string $pageName): array
    {
        $params[$pageName] = max(1, (int)($params[$pageName] ?? $params['page'] ?? 1));
        $params['pageSize'] = max(1, min(100, (int)($params['pageSize'] ?? $params['page_size'] ?? 10)));
        unset($params['_'], $params['t'], $params['timestamp'], $params['page_size']);
        ksort($params);

        return $params;
    }

    /**
     * 判断是否为默认用户列表参数（page=1,pageSize=10）。
     */
    private function isDefaultUserListParams(array $params, string $pageName): bool
    {
        return $this->normalizeUserListParams($params, $pageName) === [
            'page' => 1,
            'pageSize' => 10,
        ];
    }

    /**
     * 未显式传入用户 ID 时从当前 System 登录态读取；无登录态时返回 0 并保持 fail-closed 缓存隔离。
     */
    private function normalizeCurrentUserId(int $currentUserId): int
    {
        if ($currentUserId > 0) {
            return $currentUserId;
        }

        return (int)(user(SystemUser::class)?->getId() ?? 0);
    }
}
