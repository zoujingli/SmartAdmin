#!/usr/bin/env php
<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
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
$isPharRuntime = Phar::running(false) !== '';
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
    $isStartCommand = in_array('start', $_SERVER['argv'] ?? [], true);

    // 初始化 Hyperf 类加载器；运行目录必须在容器创建前准备好，确保日志、缓存和静态根路径可写。
    ClassLoader::init();

    is_dir($publicPath = runpath('public')) || mkdir($publicPath, 0755, true);
    is_dir($runtimePath = runpath('runtime')) || mkdir($runtimePath, 0755, true);

    // Phar 包首次 start 时按需发布前端资源；其它 CLI 命令不写 public，避免 list、升级、维护命令产生额外副作用。
    if ($isPharRuntime && $isStartCommand && !FrontendPublisher::publicReady()) {
        FrontendPublisher::publish(false, static fn (string $message): false|int => fwrite(STDOUT, '[publish] ' . $message . PHP_EOL));
    }

    if ($isPharRuntime && $isStartCommand) {
        preloadPharRuntimeClassmap();
    }

    // 运行系统服务。
    $container = require (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__)) . '/config/container.php';
    $container->get(ApplicationInterface::class)->run();
})();

/**
 * 生产 Phar 以权威 classmap 运行，长驻服务在 Worker 请求阶段不应再并发懒加载源码。
 *
 * Swoole 多 Worker 同时从 Phar 流首次读取类文件时，少数 SFX 运行时会出现读偏移错位，表现为随机
 * “No entry or class found” 或源码 parse error。启动期在 Manager 进程预加载 classmap，可让 Worker
 * fork 后直接继承已加载类，避免请求高峰首次 include Phar 内文件。
 */
function preloadPharRuntimeClassmap(): void
{
    $classmapFile = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__)) . '/vendor/composer/autoload_classmap.php';
    if (!is_file($classmapFile)) {
        return;
    }

    $classmap = require $classmapFile;
    if (!is_array($classmap)) {
        return;
    }

    foreach ($classmap as $class => $file) {
        if (!is_string($class) || !is_string($file)) {
            continue;
        }
        if (!shouldPreloadPharRuntimeClass($class)) {
            continue;
        }
        if (!class_exists($class, false) && !interface_exists($class, false) && !trait_exists($class, false)) {
            try {
                class_exists($class) || interface_exists($class) || trait_exists($class);
            } catch (Throwable) {
                // 单个可选扩展或平台相关类加载失败时不能阻断服务启动，继续预热其它核心类。
                continue;
            }
        }
    }
}

/**
 * 限定 Phar 预热范围，只处理请求阶段高频懒加载的业务类和核心运行时类。
 *
 * 全量 classmap 会提前触发 DBAL 等可选兼容层的声明检查，而这些类在常规请求中并不需要加载。
 * 这里保持白名单，既避免 Worker 并发首次读取业务源码，也不改变可选依赖的原有按需加载语义。
 */
function shouldPreloadPharRuntimeClass(string $class): bool
{
    if (str_starts_with($class, 'Hyperf\Database\DBAL\\')) {
        return false;
    }

    if (str_starts_with($class, 'Hyperf\\')) {
        return true;
    }

    foreach (['App\\','Library\\','Plugin\\','System\\'] as $prefix) {
        if (str_starts_with($class, $prefix)) {
            return true;
        }
    }

    return false;
}
