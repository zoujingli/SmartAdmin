<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */
use Hyperf\Framework\Bootstrap\PipeMessageCallback;
use Hyperf\Framework\Bootstrap\ServerStartCallback;
use Hyperf\Framework\Bootstrap\WorkerExitCallback;
use Hyperf\Framework\Bootstrap\WorkerStartCallback;
use Hyperf\HttpServer\Server as HttpServer;
use Hyperf\Server\Event;
use Hyperf\Server\Server;
use Hyperf\Server\ServerInterface;
use Hyperf\WebSocketServer\Server as WebSocketServer;
use Swoole\Constant;

use function Hyperf\Support\env;

return [
    'type' => Server::class,
    'mode' => SWOOLE_BASE,
    'servers' => [
        [
            'name' => 'http',
            'type' => ServerInterface::SERVER_WEBSOCKET,
            'host' => '0.0.0.0',
            'port' => intval(env('APP_WORKER_PORT')) ?: 9501,
            'sock_type' => SWOOLE_SOCK_TCP,
            'callbacks' => [
                Event::ON_REQUEST => [HttpServer::class, 'onRequest'],
                Event::ON_HAND_SHAKE => [WebSocketServer::class, 'onHandShake'],
                Event::ON_MESSAGE => [WebSocketServer::class, 'onMessage'],
                Event::ON_CLOSE => [WebSocketServer::class, 'onClose'],
            ],
            'settings' => [
                'heartbeat_idle_time' => 60,
                'heartbeat_check_interval' => 30,
            ],
        ],
    ],
    'settings' => [
        Constant::OPTION_PID_FILE => runpath('runtime/server.pid'),
        Constant::OPTION_WORKER_NUM => max(intval(env('APP_WORKER_NUMS', 0)) ?: swoole_cpu_num(), 2),
        Constant::OPTION_DOCUMENT_ROOT => runpath('public'),
        Constant::OPTION_OPEN_TCP_NODELAY => true,
        Constant::OPTION_ENABLE_COROUTINE => true,
        Constant::OPTION_OPEN_HTTP2_PROTOCOL => true,
        Constant::OPTION_ENABLE_STATIC_HANDLER => true,
        Constant::OPTION_MAX_REQUEST => 10000,
        Constant::OPTION_MAX_COROUTINE => 10000,
        Constant::OPTION_SOCKET_BUFFER_SIZE => 2 * 1024 * 1024,
        Constant::OPTION_BUFFER_OUTPUT_SIZE => 2 * 1024 * 1024,
        Constant::OPTION_PACKAGE_MAX_LENGTH => 10 * 1024 * 1024,
    ],
    'callbacks' => [
        Event::ON_BEFORE_START => [ServerStartCallback::class, 'beforeStart'],
        Event::ON_WORKER_START => [WorkerStartCallback::class, 'onWorkerStart'],
        Event::ON_PIPE_MESSAGE => [PipeMessageCallback::class, 'onPipeMessage'],
        Event::ON_WORKER_EXIT => [WorkerExitCallback::class, 'onWorkerExit'],
    ],
];
