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
use Library\Auth\Token;
use Library\Interfaces\UserModelInterface;
use Psr\SimpleCache\CacheInterface;
use System\Model\SystemUser;

/**
 * 基于 Hyperf Cache 维护在线用户索引。
 *
 * 这里不依赖 Redis 的集合能力，而是统一缓存一份会话索引，
 * 让 `file`、`redis`、`memory` 等驱动都可以切换使用。
 */
final class OnlineUserService
{
    private const INDEX_KEY = 'system:online-users';

    private const CACHE_GRACE_SECONDS = 60;

    private const LOCK_FILE = 'system-online-users.lock';

    /**
     * @param CacheInterface $cache 在线索引缓存
     * @param Token $token Token 服务
     */
    public function __construct(
        private CacheInterface $cache,
        private Token $token,
    ) {}

    /**
     * 标记用户会话在线。
     */
    public function markOnline(UserModelInterface $user, ?string $rawToken = null, ?int $ttl = null): void
    {
        $rawToken = $rawToken ?: $this->token->getHeaderToken();
        if ($rawToken === '') {
            return;
        }

        $ttl = max(1, $ttl ?? $this->resolveTtl($rawToken));
        $this->withIndexLock(function () use ($user, $rawToken, $ttl): void {
            $now = time();
            $entries = $this->getActiveEntriesWithoutLock();
            $entries[$this->getTokenKey($rawToken)] = [
                'user_id' => (int)$user->getId(),
                'username' => $user->getName(),
                'nickname' => $user instanceof SystemUser ? (string)($user->nickname ?: $user->username) : $user->getName(),
                'user_model' => get_class($user),
                'last_active_at' => date('Y-m-d H:i:s', $now),
                'expires_at' => $now + $ttl,
            ];

            $this->saveEntries($entries);
        });
    }

    /**
     * 在当前协程内刷新一次在线状态。
     */
    public function touchCurrent(UserModelInterface $user, ?string $rawToken = null): void
    {
        $rawToken = $rawToken ?: $this->token->getHeaderToken();
        if ($rawToken === '') {
            return;
        }

        $contextKey = 'system_online_touched_' . $this->getTokenKey($rawToken);
        if (Context::has($contextKey)) {
            return;
        }

        $this->markOnline($user, $rawToken);
        Context::set($contextKey, true);
    }

    /**
     * 标记当前 token 离线。
     */
    public function markOffline(?string $rawToken = null): void
    {
        $rawToken = $rawToken ?: $this->token->getHeaderToken();
        if ($rawToken === '') {
            return;
        }

        $this->withIndexLock(function () use ($rawToken): void {
            $entries = $this->getActiveEntriesWithoutLock();
            unset($entries[$this->getTokenKey($rawToken)]);
            $this->saveEntries($entries);
        });
    }

    /**
     * 标记指定用户全部会话离线。
     */
    public function markUserOffline(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $this->withIndexLock(function () use ($userId): void {
            $entries = array_filter(
                $this->getActiveEntriesWithoutLock(),
                static fn (array $entry): bool => (int)($entry['user_id'] ?? 0) !== $userId
            );

            $this->saveEntries($entries);
        });
    }

    /**
     * 清空在线索引。
     */
    public function clearAll(): void
    {
        $this->withIndexLock(function (): void {
            $this->cache->delete(self::INDEX_KEY);
        });
    }

    /**
     * @return array{
     *   user_count:int,
     *   session_count:int,
     *   users:array<int, array{
     *     user_id:int,
     *     username:string,
     *     nickname:string,
     *     user_model:string,
     *     last_active_at:string,
     *     expires_at:int
     *   }>
     * }
     */
    public function getSummary(int $limit = 10): array
    {
        $entries = $this->withIndexLock(fn (): array => $this->sortEntries($this->getActiveEntriesWithoutLock()));
        $userIds = [];
        foreach ($entries as $entry) {
            $userIds[(int)($entry['user_id'] ?? 0)] = true;
        }

        return [
            'user_count' => count($userIds),
            'session_count' => count($entries),
            'users' => $limit > 0 ? array_slice(array_values($entries), 0, $limit) : array_values($entries),
        ];
    }

    /**
     * 统计当前在线用户数（去重后）。
     */
    public function countOnlineUsers(): int
    {
        return $this->getSummary(0)['user_count'];
    }

    /**
     * @return array<string, array{
     *   user_id:int,
     *   username:string,
     *   nickname:string,
     *   user_model:string,
     *   last_active_at:string,
     *   expires_at:int
     * }>
     */
    private function getActiveEntriesWithoutLock(): array
    {
        $entries = $this->readEntries();
        if (!is_array($entries)) {
            return [];
        }

        $now = time();
        $active = array_filter($entries, static fn (mixed $entry): bool => is_array($entry) && (int)($entry['expires_at'] ?? 0) > $now);

        if (count($active) !== count($entries)) {
            $this->saveEntries($active);
        }

        /* @var array<string, array{user_id:int,username:string,nickname:string,user_model:string,last_active_at:string,expires_at:int}> $active */
        return $active;
    }

    /**
     * 读取在线索引；文件缓存并发写入或进程中断可能留下半截序列化内容，读取失败时清理后重建。
     */
    private function readEntries(): mixed
    {
        try {
            return $this->cache->get(self::INDEX_KEY);
        } catch (\Throwable) {
            try {
                $this->cache->delete(self::INDEX_KEY);
            } catch (\Throwable) {
                // 清理失败也不能影响主流程，下一次写入会覆盖在线索引。
            }

            return [];
        }
    }

    /**
     * @param array<string, array{
     *   user_id:int,
     *   username:string,
     *   nickname:string,
     *   user_model:string,
     *   last_active_at:string,
     *   expires_at:int
     * }> $entries
     */
    private function saveEntries(array $entries): void
    {
        if ($entries === []) {
            $this->cache->delete(self::INDEX_KEY);

            return;
        }

        $maxExpiresAt = max(array_map(static fn (array $entry): int => (int)$entry['expires_at'], $entries));
        $ttl = max(self::CACHE_GRACE_SECONDS, $maxExpiresAt - time() + self::CACHE_GRACE_SECONDS);

        $this->cache->set(self::INDEX_KEY, $entries, $ttl);
    }

    /**
     * 用本地文件锁保护在线索引的读改写，避免多进程同时写文件缓存造成序列化内容损坏。
     */
    private function withIndexLock(callable $callback): mixed
    {
        // 锁文件放在 runtime 下；目录不可写时回退系统临时目录，保证线上异常时仍可继续服务。
        $basePath = defined('BASE_PATH') ? BASE_PATH : sys_get_temp_dir();
        $directory = $basePath . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'cache-locks';
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            $directory = sys_get_temp_dir();
        }

        $handle = @fopen($directory . DIRECTORY_SEPARATOR . self::LOCK_FILE, 'c');
        if ($handle === false) {
            return $callback();
        }

        $locked = false;
        try {
            $locked = flock($handle, LOCK_EX);
            if (!$locked) {
                return $callback();
            }

            return $callback();
        } finally {
            if ($locked) {
                flock($handle, LOCK_UN);
            }
            fclose($handle);
        }
    }

    /**
     * 解析 token 对应的动态 TTL。
     */
    private function resolveTtl(string $rawToken): int
    {
        try {
            $ttl = $this->token->getTokenDynamicCacheTime($rawToken);

            return $ttl > 0 ? $ttl : max(1, $this->token->getTTL());
        } catch (\Throwable) {
            return max(1, $this->token->getTTL());
        }
    }

    /**
     * 生成 token 索引键。
     */
    private function getTokenKey(string $rawToken): string
    {
        return sha1($rawToken);
    }

    /**
     * @param array<string, array{
     *   user_id:int,
     *   username:string,
     *   nickname:string,
     *   user_model:string,
     *   last_active_at:string,
     *   expires_at:int
     * }> $entries
     * @return array<string, array{
     *   user_id:int,
     *   username:string,
     *   nickname:string,
     *   user_model:string,
     *   last_active_at:string,
     *   expires_at:int
     * }>
     */
    private function sortEntries(array $entries): array
    {
        uasort($entries, static fn (array $left, array $right): int => [$right['last_active_at'], $right['user_id']] <=> [$left['last_active_at'], $left['user_id']]);

        return $entries;
    }
}
