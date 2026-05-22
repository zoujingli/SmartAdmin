<?php

declare(strict_types=1);

namespace Plugin\WechatService\Support;

use We\Contract\StoreCacheInterface;

/**
 * 微信开放平台 SDK 在 SmartAdmin 内的缓存适配器。
 *
 * 读写统一透传项目 `_cache()`，锁当前使用本进程回调兜底；如部署到多机集群，
 * 应替换为支持共享缓存与原子锁的 CacheStore 实现，避免 access_token 并发刷新。
 */
final class HyperfCacheStore implements StoreCacheInterface
{
    public function get(string $key, mixed $default = null): mixed
    {
        $value = _cache($key);

        return $value === null ? $default : $value;
    }

    public function set(string $key, mixed $value, int $ttl): void
    {
        _cache($key, $value, max(1, $ttl));
    }

    public function del(string $key): void
    {
        _cache($key, null);
    }

    public function lock(string $key, int $ttl, callable $callback): mixed
    {
        return $callback();
    }
}
