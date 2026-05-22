#!/usr/bin/env php
<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://github.com/zoujingli/SmartAdmin/blob/master/readme.md
 */

use Hyperf\Contract\ApplicationInterface;
use Hyperf\Di\ClassLoader;
use Library\Support\FrontendPublisher;

/**
 * Hyperf 启动脚本。
 *
 * 源码模式服务于开发和构建扫描，保留较高内存并关闭 CLI OPcache；Phar 生产包尊重运行环境 OPcache，
 * 同时降低默认内存上限，避免长驻容器被启动脚本强行放大到开发配置。
 */
$isPharRuntime = \Phar::running(false) !== '';
@ini_set('memory_limit', $isPharRuntime ? '512M' : '2G');
@ini_set('display_errors', 'on');
@ini_set('display_startup_errors', 'on');
@ini_set('xdebug.max_nesting_level', 10000);

if (!$isPharRuntime) {
    if (function_exists('opcache_reset')) {
        opcache_reset();
    }
    @ini_set('opcache.enable', '0');
    @ini_set('opcache.enable_cli', '0');
    @ini_set('opcache.validate_timestamps', '1');
    @ini_set('opcache.revalidate_freq', '0');
}

date_default_timezone_set('Asia/Shanghai');
error_reporting(E_ALL);

defined('BASE_PATH') || define('BASE_PATH', dirname(__DIR__));
defined('START_TIME') || define('START_TIME', time());
defined('SWOOLE_HOOK_FLAGS') || define('SWOOLE_HOOK_FLAGS', SWOOLE_HOOK_ALL);

require (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__)) . '/vendor/autoload.php';

(function () use ($isPharRuntime): void {
    // 初始化 Hyperf 类加载器；运行目录必须在容器创建前准备好，确保日志、缓存和静态根路径可写。
    ClassLoader::init();

    is_dir($publicPath = runpath('public')) || mkdir($publicPath, 0755, true);
    is_dir($runtimePath = runpath('runtime')) || mkdir($runtimePath, 0755, true);

    // Phar 包首次 start 时按需发布前端资源；其它 CLI 命令不写 public，避免 list、升级、维护命令产生额外副作用。
    if ($isPharRuntime && in_array('start', $_SERVER['argv'] ?? [], true) && !FrontendPublisher::publicReady()) {
        FrontendPublisher::publish(false, static fn (string $message): int|false => fwrite(STDOUT, '[publish] ' . $message . PHP_EOL));
    }

    if ($isPharRuntime) {
        // SFX/Phar 的 WebSocket 关闭回调可能在 Worker 内首次触发；启动期预加载连接收集器，避免连接关闭时懒加载失败导致 Worker 退出。
        class_exists(\Hyperf\WebSocketServer\Collector\Fd::class);
        class_exists(\Hyperf\WebSocketServer\Collector\FdCollector::class);
    }

    // 运行系统服务。
    $container = require (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__)) . '/config/container.php';
    $container->get(ApplicationInterface::class)->run();
})();
