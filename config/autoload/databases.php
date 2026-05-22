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

return [
    'default' => [
        'host' => env('DB_HOST', 'localhost'),
        'port' => env('DB_PORT', 3306),
        'odbc' => env('ODBC_ENABLE', false),
        'driver' => env('DB_DRIVER', 'mysql'),
        'database' => env('DB_DATABASE', 'hyperf'),
        'username' => env('DB_USERNAME', 'root'),
        'password' => env('DB_PASSWORD'),
        'charset' => env('DB_CHARSET', 'utf8mb4'),
        'collation' => env('DB_COLLATION', 'utf8mb4_general_ci'),
        'prefix' => env('DB_PREFIX', ''),
        'pool' => [
            'heartbeat' => -1,
            'wait_timeout' => 3.0,
            'max_idle_time' => (float)env('DB_MAX_IDLE_TIME', 60),
            'min_connections' => 2,
            'max_connections' => 50,
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
    ],
];
