<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://github.com/zoujingli/SmartAdmin/blob/master/readme.md
 */
use function Hyperf\Support\env;

return [
    'default' => [
        'auth' => env('REDIS_AUTH'),
        'host' => env('REDIS_HOST', 'localhost'),
        'port' => (int)env('REDIS_PORT', 6379),
        'db' => (int)env('REDIS_DB', 0),
        // 连接超时时间（秒），0表示不超时，建议设置为5秒
        'timeout' => (float)env('REDIS_TIMEOUT', 5.0),
        'reserved' => null,
        // 读取超时时间（秒），0表示不超时，建议设置为5秒
        'read_timeout' => (float)env('REDIS_READ_TIMEOUT', 5.0),
        // 重试间隔（毫秒），连接失败后重试的间隔时间
        'retry_interval' => (int)env('REDIS_RETRY_INTERVAL', 100),
        'cluster' => [
            'enable' => (bool)env('REDIS_CLUSTER_ENABLE', false),
            'name' => null,
            'seeds' => [],
        ],
        'sentinel' => [
            'enable' => (bool)env('REDIS_SENTINEL_ENABLE', false),
            'master_name' => env('REDIS_MASTER_NAME', 'mymaster'),
            'nodes' => explode(';', env('REDIS_SENTINEL_NODE', '')),
            'persistent' => '',
            'read_timeout' => (int)env('REDIS_SENTINEL_READ_TIMEOUT', 5),
            'auth' => env('REDIS_SENTINEL_PASSWORD', ''),
        ],
        'pool' => [
            // 心跳检测间隔（秒），-1表示不检测，建议设置为30秒
            'heartbeat' => (int)env('REDIS_HEARTBEAT', 30),
            // 最小连接数，预创建连接以应对突发请求
            'min_connections' => (int)env('REDIS_MIN_CONNECTIONS', 5),
            // 最大连接数，根据并发需求调整（推荐：Worker数 * 5-10）
            'max_connections' => (int)env('REDIS_MAX_CONNECTIONS', 50),
            // 连接超时时间（秒）
            'connect_timeout' => (float)env('REDIS_CONNECT_TIMEOUT', 10.0),
            // 等待连接的超时时间（秒），当连接池满时等待可用连接的时间
            'wait_timeout' => (float)env('REDIS_WAIT_TIMEOUT', 5.0),
            // 连接最大空闲时间（秒），超过此时间的空闲连接将被回收
            'max_idle_time' => (float)env('REDIS_MAX_IDLE_TIME', 60),
        ],
        'options' => [
            'prefix' => env('REDIS_PREFIX', ''),
        ],
        'event' => [
            'enable' => (bool)env('REDIS_EVENT_ENABLE', false),
        ],
    ],
];
