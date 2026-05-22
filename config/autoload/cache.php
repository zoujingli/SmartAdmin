<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://github.com/zoujingli/SmartAdmin/blob/master/readme.md
 */
use Hyperf\Cache\Driver\CoroutineMemoryDriver;
use Hyperf\Cache\Driver\MemoryDriver;
use Hyperf\Cache\Driver\RedisDriver;
use Hyperf\Codec\Packer\PhpSerializerPacker;
use Library\Cache\Driver\RuntimeFileSystemDriver;
use Library\Support\CacheDriverResolver;

use function Hyperf\Support\env;

$cacheStore = CacheDriverResolver::effectiveStoreKey();

$cachePrefix = rtrim((string)(getenv('CACHE_PREFIX') ?: ($_ENV['CACHE_PREFIX'] ?? $_SERVER['CACHE_PREFIX'] ?? env('CACHE_PREFIX', 'hyadmin'))), ':') . ':';

$fileCacheDirectory = runpath('runtime/cache');
if (!is_dir($fileCacheDirectory) && !@mkdir($fileCacheDirectory, 0755, true) && !is_dir($fileCacheDirectory)) {
    throw new RuntimeException(sprintf('Unable to create cache directory: %s', $fileCacheDirectory));
}

return [
    'default' => 'default',
    'stores' => (function () use ($cachePrefix, $cacheStore, $fileCacheDirectory) {
        $stores = [
            'file' => [
                'driver' => RuntimeFileSystemDriver::class,
                'packer' => PhpSerializerPacker::class,
                'prefix' => $cachePrefix,
                'store_path' => $fileCacheDirectory,
                'skip_cache_results' => [],
            ],
            'redis' => [
                'driver' => RedisDriver::class,
                'packer' => PhpSerializerPacker::class,
                'prefix' => $cachePrefix,
                'skip_cache_results' => [],
                'options' => [
                    'pool' => (string)(getenv('CACHE_REDIS_POOL') ?: ($_ENV['CACHE_REDIS_POOL'] ?? $_SERVER['CACHE_REDIS_POOL'] ?? env('CACHE_REDIS_POOL', 'default'))),
                ],
            ],
            'memory' => [
                'driver' => MemoryDriver::class,
                'packer' => PhpSerializerPacker::class,
                'prefix' => $cachePrefix,
                'skip_cache_results' => [],
            ],
            'coroutine_memory' => [
                'driver' => CoroutineMemoryDriver::class,
                'packer' => PhpSerializerPacker::class,
                'prefix' => $cachePrefix,
                'skip_cache_results' => [],
            ],
        ];

        $selectedStore = array_key_exists($cacheStore, $stores) ? $cacheStore : 'file';
        $stores['default'] = $stores[$selectedStore];

        return $stores;
    })(),
];
