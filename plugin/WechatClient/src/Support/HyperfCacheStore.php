<?php

declare(strict_types=1);

namespace Plugin\WechatClient\Support;

use We\Contract\StoreCacheInterface;

/**
 * 微信 SDK 在 SmartAdmin 内的缓存适配器。
 *
 * 读写统一透传项目 `_cache()`，锁当前使用本进程回调兜底；如部署到多机集群，
 * 应替换为支持共享缓存与原子锁的 CacheStore 实现，避免 access_token 并发刷新。
 */
final class HyperfCacheStore implements StoreCacheInterface
{
    /**
     * 读取 SDK 缓存值。
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = _cache($key);

        return $value === null ? $default : $value;
    }

    /**
     * 写入 SDK 缓存值。
     */
    public function set(string $key, mixed $value, int $ttl): void
    {
        _cache($key, $value, max(1, $ttl));
    }

    /**
     * 删除 SDK 缓存值。
     */
    public function del(string $key): void
    {
        _cache($key, null);
    }

    /**
     * 执行 SDK 锁回调；当前项目缓存封装未暴露分布式锁，因此直接执行回调。
     */
    public function lock(string $key, int $ttl, callable $callback): mixed
    {
        return $callback();
    }
}
