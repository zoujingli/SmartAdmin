<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://github.com/zoujingli/SmartAdmin/blob/master/readme.md
 */
use Hyperf\Database\Commands\Ast\ModelRewriteKeyInfoVisitor;
use Hyperf\Database\Commands\Ast\ModelRewriteSoftDeletesVisitor;
use Hyperf\Database\Commands\Ast\ModelRewriteTimestampsVisitor;

use function Hyperf\Support\env;

$driver = strtolower((string)env('DB_DRIVER', 'sqlite'));
$isSqlite = $driver === 'sqlite';
$database = (string)env('DB_DATABASE', $isSqlite ? 'runtime/system.db' : 'hyadmin');
if ($isSqlite && $database !== ':memory:' && ! str_starts_with($database, '/')) {
    // SQLite 是默认免 MySQL 体验库，相对路径固定解析到项目目录，避免命令在不同 cwd 下创建多份数据库。
    $database = BASE_PATH . '/' . ltrim($database, '/');
}

$connection = [
    'driver' => $driver,
    'database' => $database,
    'prefix' => env('DB_PREFIX', ''),
    'pool' => [
        'heartbeat' => -1,
        'wait_timeout' => 3.0,
        'max_idle_time' => (float)env('DB_MAX_IDLE_TIME', 60),
        'min_connections' => (int)env('DB_MIN_CONNECTIONS', $isSqlite ? 1 : 2),
        'max_connections' => (int)env('DB_MAX_CONNECTIONS', $isSqlite ? 1 : 50),
        'connect_timeout' => 10.0,
    ],
    'commands' => [
        'gen:model' => [
            'path' => 'app/Model',
            'uses' => 'Library\CoreModel',
            'force_casts' => true,
            'inheritance' => 'CoreModel',
            'with_comments' => true,
            'refresh_fillable' => true,
            'visitors' => [
                ModelRewriteKeyInfoVisitor::class,
                ModelRewriteTimestampsVisitor::class,
                ModelRewriteSoftDeletesVisitor::class,
            ],
        ],
    ],
];

if (! $isSqlite) {
    // 显式配置 MySQL 时保留原连接参数；默认 SQLite 不携带 host，避免连接工厂按多主机模式解析。
    $connection = array_merge($connection, [
        'host' => env('DB_HOST', 'localhost'),
        'port' => env('DB_PORT', 3306),
        'odbc' => env('ODBC_ENABLE', false),
        'username' => env('DB_USERNAME', 'root'),
        'password' => env('DB_PASSWORD'),
        'charset' => env('DB_CHARSET', 'utf8mb4'),
        'collation' => env('DB_COLLATION', 'utf8mb4_general_ci'),
    ]);
}

return [
    'default' => $connection,
];
