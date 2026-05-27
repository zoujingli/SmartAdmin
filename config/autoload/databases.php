<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */
use Hyperf\Database\Commands\Ast\ModelRewriteKeyInfoVisitor;
use Hyperf\Database\Commands\Ast\ModelRewriteSoftDeletesVisitor;
use Hyperf\Database\Commands\Ast\ModelRewriteTimestampsVisitor;

use function Hyperf\Support\env;

$driver = strtolower((string)env('DB_DRIVER', 'sqlite'));
$isSqlite = $driver === 'sqlite';
$database = (string)env('DB_DATABASE', $isSqlite ? 'runtime/system.db' : 'smartadmin');
$isFileSqlite = $isSqlite && $database !== ':memory:';

if ($isFileSqlite && !str_starts_with($database, '/')) {
    $database = runpath('runtime/system.db');
}
if ($isFileSqlite) {
    // 二进制模式没有源码包装器，首次执行 release install/restore 时按需补齐空库文件。
    is_dir(dirname($database)) || mkdir(dirname($database), 0755, true);
    is_file($database) || touch($database);
}

$connection = [
    'driver' => $driver,
    'database' => $database,
    'prefix' => env('DB_PREFIX', ''),
    'pool' => [
        'heartbeat' => -1,
        'wait_timeout' => 3.0,
        'max_idle_time' => 60.0,
        'min_connections' => 1,
        'max_connections' => $isSqlite ? 1 : 10,
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

if (!$isSqlite) {
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

return ['default' => $connection];
