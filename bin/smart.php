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
use Swoole\Event;
use Swoole\Process;
use Swoole\Timer;

/**
 * SmartAdmin 统一开发入口。
 *
 * 该脚本只负责 Swoole CLI 运行时选择、SQLite 本地库准备和命令分派，不初始化 Hyperf 容器；
 * watch 会二次进入已选中的 Swoole CLI 进程内运行，避免通过 Hyperf 命令启动时事件循环已创建导致监听器无法管理子进程。
 */
final class SmartEntrypoint
{
    private readonly string $baseDir;

    private readonly string $scriptPath;

    /**
     * @var string[]
     */
    private array $tried = [];

    /**
     * @param string[] $argv
     */
    public function __construct(private readonly array $argv)
    {
        $this->baseDir = dirname(__DIR__);
        $this->scriptPath = __FILE__;
    }

    public function run(): int
    {
        $args = $this->argv;
        array_shift($args);
        // 无参数入口面向本地开发，默认进入 watch 管理模式；生产或一次性前台启动必须显式传入 start。
        $command = $args === [] ? 'watch' : (string)array_shift($args);

        return match ($command) {
            'sqlite' => $this->prepareSqlite($args),
            'runtime' => $this->runRuntime($args),
            'composer' => $this->runComposer($args),
            'watch' => $this->runWatch($args),
            '__watch' => $this->runInternalWatch($args),
            default => $this->runHyperf($command, $args),
        };
    }

    /**
     * @param string[] $args
     */
    private function prepareSqlite(array $args): int
    {
        if ($args !== []) {
            fwrite(STDERR, 'Usage: ./bin/smart.php sqlite' . PHP_EOL);
            return 64;
        }

        // SQLite 包装器只补齐缺失的本地库文件，不覆盖用户已有数据，也不在非 SQLite 驱动下产生副作用。
        $envFile = $this->baseDir . '/.env';
        if (!is_file($envFile)) {
            $envFile = $this->baseDir . '/.env.example';
        }

        $driver = strtolower((string)($this->readEnv('DB_DRIVER', $envFile) ?? 'sqlite'));
        if ($driver !== 'sqlite') {
            return 0;
        }

        $database = $this->readEnv('DB_DATABASE', $envFile) ?: 'runtime/system.db';
        if ($database === ':memory:') {
            return 0;
        }

        $path = str_starts_with($database, '/') ? $database : $this->baseDir . '/' . $database;
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            fwrite(STDERR, 'Cannot create SQLite directory: ' . $dir . PHP_EOL);
            return 1;
        }
        if (!file_exists($path) && @file_put_contents($path, '') === false) {
            fwrite(STDERR, 'Cannot create SQLite database: ' . $path . PHP_EOL);
            return 1;
        }

        return 0;
    }

    /**
     * @param string[] $args
     */
    private function runRuntime(array $args): int
    {
        return $this->execute($this->selectRunner(), $args);
    }

    /**
     * @param string[] $args
     */
    private function runComposer(array $args): int
    {
        $composer = $this->resolveExecutable('composer');
        if ($composer === null) {
            fwrite(STDERR, 'Composer executable not found in PATH.' . PHP_EOL);
            return 127;
        }

        return $this->execute($this->selectRunner(), array_merge([$composer], $args));
    }

    /**
     * @param string[] $args
     */
    private function runWatch(array $args): int
    {
        if ($args !== []) {
            fwrite(STDERR, 'Usage: ./bin/smart.php watch' . PHP_EOL);
            return 64;
        }

        $runner = $this->selectRunner();
        // watch 自身必须在选定的 Swoole CLI 内直接运行，避免通过 Hyperf 命令启动后事件循环已创建再调整 Swoole 设置。
        return $this->execute($runner, [$this->scriptPath, '__watch', $runner]);
    }

    /**
     * @param string[] $args
     */
    private function runInternalWatch(array $args): int
    {
        $runner = $args[0] ?? $this->selectRunner();
        if (!extension_loaded('swoole') && !extension_loaded('openswoole')) {
            fwrite(STDERR, 'watch requires a Swoole CLI runtime.' . PHP_EOL);
            return 1;
        }
        if (!function_exists('exec')) {
            fwrite(STDERR, 'exec 函数被禁用，请在 php.ini 中启用后重试。' . PHP_EOL);
            return 1;
        }

        @ini_set('memory_limit', '2G');
        date_default_timezone_set('Asia/Shanghai');
        // watch 会在定时器回调中重启 Worker 子进程；必须在事件循环创建前关闭 Swoole 定时器协程化，
        // 否则 Timer 回调处于协程上下文时调用 Process::start() 会触发 “must be forked outside the coroutine”。
        function_exists('swoole_async_set') && swoole_async_set([
            'log_level' => defined('SWOOLE_LOG_INFO') ? SWOOLE_LOG_INFO : 2,
            'enable_coroutine' => false,
        ]);

        return (new SmartWatchRunner($this->baseDir, $runner))->run();
    }

    /**
     * @param string[] $args
     */
    private function runHyperf(string $command, array $args): int
    {
        return $this->execute($this->selectRunner(), array_merge([$this->baseDir . '/bin/hyperf.php', $command], $args));
    }

    private function selectRunner(): string
    {
        // 运行时选择顺序固定为显式环境变量优先，其次仓库内置二进制，最后才回退系统命令。
        $osName = php_uname('s') ?: 'unknown';
        $archName = $this->normalizeArch(php_uname('m') ?: 'unknown');

        foreach (['SWOOLE_CLI_BIN', 'SWOOLE_CLI'] as $envName) {
            $value = getenv($envName);
            if (is_string($value) && $this->tryRunner($value)) {
                return end($this->tried) ?: $value;
            }
        }

        $platformRunner = match ($osName . ':' . $archName) {
            'Darwin:a64' => $this->baseDir . '/bin/swoole-macos-a64',
            'Darwin:x64' => $this->baseDir . '/bin/swoole-macos-x64',
            'Linux:a64' => $this->baseDir . '/bin/swoole-linux-a64',
            'Linux:x64' => $this->baseDir . '/bin/swoole-linux-x64',
            default => null,
        };
        if ($platformRunner !== null && $this->tryRunner($platformRunner)) {
            return end($this->tried) ?: $platformRunner;
        }

        foreach (['swoole-cli', 'swoole', 'php'] as $candidate) {
            if ($this->tryRunner($candidate)) {
                return end($this->tried) ?: $candidate;
            }
        }

        fwrite(STDERR, 'No compatible Swoole CLI runtime found.' . PHP_EOL);
        fwrite(STDERR, 'Detected platform: ' . $osName . '/' . $archName . PHP_EOL);
        fwrite(STDERR, 'Tried: ' . ($this->tried === [] ? 'none' : implode(', ', $this->tried)) . PHP_EOL);
        fwrite(STDERR, 'Set SWOOLE_CLI_BIN=/path/to/php-with-swoole or add a matching bin/swoole-* runtime.' . PHP_EOL);
        exit(127);
    }

    private function tryRunner(string $candidate): bool
    {
        if ($candidate === '') {
            return false;
        }

        $resolved = $this->resolveExecutable($candidate);
        if ($resolved === null) {
            $this->tried[] = $candidate;
            return false;
        }

        $resolved = $this->absolutePath($resolved);
        $this->tried[] = $resolved;
        if ($resolved === $this->scriptPath) {
            return false;
        }

        if (is_file($resolved) && !is_executable($resolved)) {
            @chmod($resolved, 0755);
        }
        if (!is_executable($resolved) || !$this->isSwooleRuntime($resolved)) {
            return false;
        }

        array_pop($this->tried);
        $this->tried[] = $resolved;
        return true;
    }

    private function isSwooleRuntime(string $runner): bool
    {
        $code = 'exit((PHP_SAPI === "cli" || PHP_SAPI === "phpdbg") && (extension_loaded("swoole") || extension_loaded("openswoole")) ? 0 : 1);';
        $status = 0;
        exec(escapeshellarg($runner) . ' -r ' . escapeshellarg($code) . ' >/dev/null 2>&1', $output, $status);
        return $status === 0;
    }

    private function normalizeArch(string $arch): string
    {
        return match ($arch) {
            'arm64', 'aarch64' => 'a64',
            'x86_64', 'amd64' => 'x64',
            default => $arch,
        };
    }

    private function resolveExecutable(string $name): ?string
    {
        if (str_contains($name, '/')) {
            return is_file($name) ? $name : null;
        }

        foreach (explode(PATH_SEPARATOR, getenv('PATH') ?: '') as $dir) {
            $path = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
            if (is_file($path) && is_executable($path)) {
                return $path;
            }
        }

        return null;
    }

    private function absolutePath(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }
        if (str_contains($path, '/')) {
            $dir = realpath(dirname($path));
            if ($dir !== false) {
                return $dir . '/' . basename($path);
            }
        }
        return $path;
    }

    /**
     * @param string[] $args
     */
    private function execute(string $runner, array $args): int
    {
        // 使用 exec 替换当前包装进程，确保 start/watch 等长驻命令的信号、退出码和标准输入输出不被中间层吞掉。
        if (function_exists('pcntl_exec')) {
            pcntl_exec($runner, $args);
            fwrite(STDERR, 'Failed to exec runtime: ' . $runner . PHP_EOL);
            return 126;
        }

        $command = escapeshellarg($runner);
        foreach ($args as $argument) {
            $command .= ' ' . escapeshellarg((string)$argument);
        }
        passthru($command, $status);
        return (int)$status;
    }

    private function readEnv(string $key, string $file): ?string
    {
        if (!is_file($file)) {
            return null;
        }

        foreach ((array)file($file, FILE_IGNORE_NEW_LINES) as $line) {
            $line = trim((string)$line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (str_starts_with($line, 'export ')) {
                $line = trim(substr($line, 7));
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $name = trim(substr($line, 0, $pos));
            if ($name !== $key) {
                continue;
            }
            $value = trim(substr($line, $pos + 1));
            if ($value !== '' && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
                $value = substr($value, 1, -1);
            }
            return $value;
        }

        return null;
    }
}

/**
 * 开发进程状态检查器。
 *
 * Process::kill($pid, 0) 对 zombie 进程也会返回存在；watch 接管时这类进程已经退出且不可再 kill，
 * 只能等待父进程回收，因此需要用 ps 状态把它们视为已退出，避免阻塞新 watch 启动。
 */
final class SmartWatchProcessInspector
{
    public static function isRunning(int $pid): bool
    {
        if ($pid <= 0 || !@Process::kill($pid, 0)) {
            return false;
        }

        return !self::isZombie($pid);
    }

    private static function isZombie(int $pid): bool
    {
        $output = [];
        $status = 0;
        @exec('ps -p ' . (int)$pid . ' -o stat=,command= 2>/dev/null', $output, $status);
        if ($status !== 0 || $output === []) {
            return false;
        }

        foreach ($output as $line) {
            $columns = preg_split('/\s+/', trim((string)$line), 2);
            $stat = $columns[0] ?? '';
            $command = $columns[1] ?? '';
            if (str_contains($stat, 'Z') || str_contains($command, '<defunct>')) {
                return true;
            }
        }

        return false;
    }
}

/**
 * 源码期 watch 运行器。
 *
 * watch 不注册为 Hyperf 命令，避免开发监听器与应用容器、协程事件循环互相污染；这里只用 Swoole Process/Event/Timer
 * 管理 Worker 子进程和文件变更轮询，生产发布包仍只通过标准 start 命令运行。
 */
final class SmartWatchRunner
{
    /**
     * @var null|resource
     */
    private mixed $watchLock = null;

    public function __construct(
        private readonly string $root,
        private readonly string $runner
    ) {}

    public function run(): int
    {
        $worker = $this->root . '/bin/hyperf.php';
        if (!is_file($worker)) {
            SmartWatchLogger::error('统一入口不存在：' . $worker . PHP_EOL);
            return 1;
        }

        $port = (int)($this->readEnv('APP_WORKER_PORT') ?: 9501);
        if (!$this->forceReleaseWatchLock($port)) {
            return 1;
        }
        if (!$this->acquireWatchLock($port)) {
            return 0;
        }

        $procManager = new SmartWatchProcessManager($this->runner, [$worker, 'start'], $worker, $port, $this->root);
        $fileWatcher = new SmartWatchFileWatcher($this->root, 'env,php', [
            '.git',
            'build',
            'migrations',
            'public',
            'runtime',
            'vendor',
            'web',
        ]);

        $procManager->start();
        $fileWatcher->init()->watch(1000, static fn () => $procManager->restart());

        Process::signal(SIGINT, static function () use ($procManager): void {
            $procManager->stop();
            Event::exit();
        });
        Process::signal(SIGTERM, static function () use ($procManager): void {
            $procManager->stop();
            Event::exit();
        });
        Process::signal(SIGCHLD, static fn () => $procManager->handleChildSignal());

        Event::wait();
        return 0;
    }

    private function readEnv(string $key): ?string
    {
        foreach ([$this->root . '/.env', $this->root . '/.env.example'] as $file) {
            if (!is_file($file)) {
                continue;
            }
            foreach ((array)file($file, FILE_IGNORE_NEW_LINES) as $line) {
                $line = trim((string)$line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                $line = str_starts_with($line, 'export ') ? trim(substr($line, 7)) : $line;
                $pos = strpos($line, '=');
                if ($pos === false || trim(substr($line, 0, $pos)) !== $key) {
                    continue;
                }
                $value = trim(substr($line, $pos + 1));
                if ($value !== '' && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
                    $value = substr($value, 1, -1);
                }
                return $value;
            }
        }

        return null;
    }

    private function forceReleaseWatchLock(int $port): bool
    {
        $pids = [];
        $lockFile = $this->root . '/runtime/watch.lock';
        if (is_file($lockFile)) {
            $content = trim((string)file_get_contents($lockFile));
            $meta = json_decode($content, true);
            $pid = is_array($meta) ? (int)($meta['pid'] ?? 0) : 0;
            if ($pid <= 0 && ctype_digit($content)) {
                $pid = (int)$content;
            }
            $pid > 0 && $pids[] = $pid;

            $output = [];
            @exec('lsof -nP -t ' . escapeshellarg($lockFile) . ' 2>/dev/null', $output);
            foreach ($output as $line) {
                $pid = trim((string)$line);
                ctype_digit($pid) && $pids[] = (int)$pid;
            }
        }

        $output = [];
        @exec('ps -eo pid=,command=', $output);
        foreach ($output as $line) {
            $line = trim((string)$line);
            if ($line === '' || !preg_match('/^(\d+)\s+(.+)$/', $line, $matches)) {
                continue;
            }

            $pid = (int)$matches[1];
            $command = str_replace('\\', '/', (string)$matches[2]);
            if (str_contains($command, $this->root . '/bin/smart.php __watch')) {
                $pids[] = $pid;
            }
        }

        $pidFile = $this->root . '/runtime/server.pid';
        if (is_file($pidFile)) {
            $pid = trim((string)file_get_contents($pidFile));
            ctype_digit($pid) && $pids[] = (int)$pid;
        }

        $portCommands = [
            'lsof -nP -iTCP:' . (int)$port . ' -sTCP:LISTEN -t 2>/dev/null',
            'fuser -n tcp ' . (int)$port . ' 2>/dev/null',
            "ss -ltnp 'sport = :" . (int)$port . "' 2>/dev/null",
        ];
        foreach ($portCommands as $command) {
            $output = [];
            @exec($command, $output);
            foreach ($output as $line) {
                if (str_starts_with($command, 'ss ')) {
                    preg_match_all('/pid=(\d+)/', (string)$line, $matches);
                } else {
                    preg_match_all('/(?:^|\D)(\d+)(?=\D|$)/', (string)$line, $matches);
                }
                if ($matches[1] ?? []) {
                    foreach ($matches[1] as $pid) {
                        $pids[] = (int)$pid;
                    }
                }
            }
        }

        return $this->killPids($pids);
    }

    private function acquireWatchLock(int $port): bool
    {
        $dir = $this->root . '/runtime';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            SmartWatchLogger::error('无法创建 watch 锁目录：' . $dir . PHP_EOL);
            return false;
        }

        $lockFile = $dir . '/watch.lock';
        $handle = @fopen($lockFile, 'c+');
        if ($handle === false) {
            SmartWatchLogger::error('无法创建 watch 锁文件：' . $lockFile . PHP_EOL);
            return false;
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            rewind($handle);
            $meta = json_decode((string)stream_get_contents($handle), true);
            $pid = is_array($meta) ? (int)($meta['pid'] ?? 0) : 0;
            SmartWatchLogger::warning(sprintf(
                '⚠️ SmartAdmin watch 已在运行%s，当前 watch 将强制接管。%s',
                $pid > 0 ? ' (pid=' . $pid . ')' : '',
                PHP_EOL
            ));
            fclose($handle);
            if (!$this->forceReleaseWatchLock($port)) {
                return false;
            }

            $handle = @fopen($lockFile, 'c+');
            if ($handle === false || !flock($handle, LOCK_EX | LOCK_NB)) {
                SmartWatchLogger::error('强制接管旧 watch 后仍无法获取锁，请手动检查 runtime/watch.lock。' . PHP_EOL);
                is_resource($handle) && fclose($handle);
                return false;
            }
        }

        rewind($handle);
        $meta = json_decode((string)stream_get_contents($handle), true);
        $pid = is_array($meta) ? (int)($meta['pid'] ?? 0) : 0;
        if ($pid > 0 && !SmartWatchProcessInspector::isRunning($pid)) {
            SmartWatchLogger::warning(sprintf(
                '⚠️ 覆盖陈旧 SmartAdmin watch 锁 (pid=%d 已不存在)。%s',
                $pid,
                PHP_EOL
            ));
        }

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode([
            'pid' => (int)getmypid(),
            'root' => $this->root,
            'started_at' => date('c'),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
        fflush($handle);
        $this->watchLock = $handle;
        return true;
    }

    /**
     * @param int[] $pids
     */
    private function killPids(array $pids): bool
    {
        $pids = array_values(array_unique(array_filter($pids, static fn (int $pid): bool => $pid > 0 && $pid !== (int)getmypid() && SmartWatchProcessInspector::isRunning($pid))));
        if ($pids === []) {
            return true;
        }

        SmartWatchLogger::warning(sprintf('⚠️ Killing PIDS [%s] with signal %d.%s', implode(',', $pids), SIGTERM, PHP_EOL));
        foreach ($pids as $pid) {
            @Process::kill($pid, SIGTERM);
        }
        $alive = $this->waitProcessesExit($pids, 3000);
        if ($alive !== []) {
            SmartWatchLogger::warning(sprintf('⚠️ Killing PIDS [%s] with signal %d.%s', implode(',', $alive), SIGKILL, PHP_EOL));
            foreach ($alive as $pid) {
                @Process::kill($pid, SIGKILL);
            }
        }
        $alive = $this->waitProcessesExit($alive, 1000);
        if ($alive !== []) {
            SmartWatchLogger::error(sprintf('停止旧 watch/后端进程失败，仍在运行的 PIDS：[%s]。%s', implode(',', $alive), PHP_EOL));
            return false;
        }

        return true;
    }

    /**
     * @param int[] $pids
     * @return int[]
     */
    private function waitProcessesExit(array $pids, int $timeoutMs): array
    {
        $deadline = microtime(true) + ($timeoutMs / 1000);
        do {
            $alive = array_values(array_filter($pids, static fn (int $pid): bool => SmartWatchProcessInspector::isRunning($pid)));
            if ($alive === []) {
                return [];
            }
            usleep(200000);
        } while (microtime(true) < $deadline);

        return array_values(array_filter($pids, static fn (int $pid): bool => SmartWatchProcessInspector::isRunning($pid)));
    }
}

/**
 * 开发 Worker 进程管理器。
 *
 * 重启前按 Hyperf 启动脚本和监听端口双重清理残留进程；若残留进程没有及时退出，会逐步升级到 SIGKILL，
 * 防止 watch 卡在端口占用清理阶段。
 */
final class SmartWatchProcessManager
{
    private ?Process $serve = null;

    private ?int $restartTimerId = null;

    /**
     * @param string[] $workerStart
     */
    public function __construct(
        private readonly string $runner,
        private readonly array $workerStart,
        private readonly string $worker,
        private readonly int $workerPort,
        private readonly string $root
    ) {}

    public function start(): void
    {
        $this->clearRestartTimer();
        if (!$this->terminateProcesses()) {
            SmartWatchLogger::error('旧开发服务未清理完成，watch 不继续启动新服务。' . PHP_EOL);
            exit(1);
        }
        SmartWatchLogger::info('🔄 Starting service...' . PHP_EOL);

        $serve = new Process(fn (Process $process) => $process->exec($this->runner, $this->workerStart), true);
        if ($serve->start() === false) {
            // 本地开发入口不能因为一次 fork 失败直接退出；保留 watch 父进程并延迟重试，便于释放端口或修复环境后自动恢复。
            SmartWatchLogger::error('启动开发服务进程失败，2 秒后重试。' . PHP_EOL);
            $this->scheduleRestart(2000);
            return;
        }

        $this->serve = $serve;
        Event::add($serve->pipe, fn () => $this->output($serve->read() ?: null));
    }

    public function restart(): void
    {
        $this->stop();
        $this->start();
    }

    public function stop(): void
    {
        $this->clearRestartTimer();
        if ($this->serve?->pid) {
            @Process::kill($this->serve->pid);
            @Event::del($this->serve->pipe);
            $this->serve = null;
        }
    }

    public function handleChildSignal(): void
    {
        // watch 子进程可能因语法错误、端口占用、Fatal 或用户态异常退出；必须回收 SIGCHLD，
        // 否则父进程无法准确判断服务已退出，开发时会表现为入口随机失效或残留僵尸进程。
        while (($status = Process::wait(false)) !== false) {
            $pid = (int)($status['pid'] ?? 0);
            if ($this->serve === null || $pid !== $this->serve->pid) {
                continue;
            }

            @Event::del($this->serve->pipe);
            $this->serve = null;

            $code = (int)($status['code'] ?? 0);
            $signal = (int)($status['signal'] ?? 0);
            SmartWatchLogger::warning(sprintf(
                '⚠️ Service process exited (pid=%d, code=%d, signal=%d). Restarting in 1 second...%s',
                $pid,
                $code,
                $signal,
                PHP_EOL
            ));
            $this->scheduleRestart(1000);
        }
    }

    private function scheduleRestart(int $delayMs): void
    {
        if ($this->restartTimerId !== null) {
            return;
        }

        $timerId = Timer::after($delayMs, function (): void {
            $this->restartTimerId = null;
            if ($this->serve !== null) {
                return;
            }
            $this->start();
        });
        if ($timerId === false) {
            SmartWatchLogger::error('创建开发服务重启定时器失败，请手动重新执行 ./bin/smart.php。' . PHP_EOL);
            return;
        }

        $this->restartTimerId = $timerId;
    }

    private function clearRestartTimer(): void
    {
        if ($this->restartTimerId === null) {
            return;
        }

        Timer::clear($this->restartTimerId);
        $this->restartTimerId = null;
    }

    private function terminateProcesses(): bool
    {
        $attempt = 0;
        do {
            $pids = $this->collectTakeoverPids();
            if ($attempt >= 6 && $pids !== []) {
                SmartWatchLogger::error(sprintf(
                    '旧开发服务接管超时，仍在运行的 PIDS [%s]。%s',
                    implode(',', $pids),
                    PHP_EOL
                ));
                return false;
            }

            $signal = $attempt >= 3 ? SIGKILL : SIGTERM;
            if ($pids !== []) {
                SmartWatchLogger::info(sprintf('🔄 Killed PIDS [%s] with signal %d.%s', implode(',', $pids), $signal, PHP_EOL));
                foreach ($pids as $pid) {
                    @Process::kill($pid, $signal);
                }
                usleep(800000);
            }
            ++$attempt;
        } while ($pids !== []);

        // 重启前清理 Hyperf 扫描缓存，确保新增注解、命令和依赖变更能被下一次 Worker 重新加载。
        $this->removeDirectory($this->root . '/runtime/container');
        sleep(1);
        return true;
    }

    /**
     * @return int[]
     */
    private function collectTakeoverPids(): array
    {
        $pids = [];
        $pidFile = $this->root . '/runtime/server.pid';
        if (is_file($pidFile)) {
            $pid = trim((string)file_get_contents($pidFile));
            ctype_digit($pid) && $pids[] = (int)$pid;
        }

        $output = [];
        @exec('ps -eo pid=,ppid=,command=', $output);
        $rows = [];
        foreach ($output as $line) {
            $line = trim((string)$line);
            if ($line === '' || !preg_match('/^(\d+)\s+(\d+)\s+(.+)$/', $line, $matches)) {
                continue;
            }

            $pid = (int)$matches[1];
            $command = str_replace('\\', '/', (string)$matches[3]);
            if ($pid === (int)getmypid()) {
                continue;
            }
            $rows[] = [$pid, (int)$matches[2], $command];
            if ($this->isProjectStartCommand($command)) {
                $pids[] = $pid;
            }
        }

        $changed = true;
        while ($changed) {
            $changed = false;
            $known = array_flip($pids);
            foreach ($rows as [$pid, $ppid]) {
                if (!isset($known[$pid]) && isset($known[$ppid])) {
                    $pids[] = $pid;
                    $changed = true;
                }
            }
        }

        $portCommands = [
            'lsof -nP -iTCP:' . (int)$this->workerPort . ' -sTCP:LISTEN -t 2>/dev/null',
            'fuser -n tcp ' . (int)$this->workerPort . ' 2>/dev/null',
            "ss -ltnp 'sport = :" . (int)$this->workerPort . "' 2>/dev/null",
        ];
        foreach ($portCommands as $command) {
            $output = [];
            @exec($command, $output);
            foreach ($output as $line) {
                if (str_starts_with($command, 'ss ')) {
                    preg_match_all('/pid=(\d+)/', (string)$line, $matches);
                } else {
                    preg_match_all('/(?:^|\D)(\d+)(?=\D|$)/', (string)$line, $matches);
                }
                foreach ($matches[1] ?? [] as $pid) {
                    $pids[] = (int)$pid;
                }
            }
        }

        return array_values(array_unique(array_filter($pids, static fn (int $pid): bool => $pid > 0 && $pid !== (int)getmypid() && SmartWatchProcessInspector::isRunning($pid))));
    }

    private function isProjectStartCommand(string $command): bool
    {
        $root = str_replace('\\', '/', $this->root);
        $worker = str_replace('\\', '/', $this->worker);
        return (bool)preg_match('/' . preg_quote($worker, '/') . '\s+start(?:\s|$)/', $command)
            || (bool)preg_match('/' . preg_quote($root . '/bin/hyperf.php', '/') . '\s+start(?:\s|$)/', $command)
            || (bool)preg_match('/' . preg_quote($root . '/bin/smart.php', '/') . '\s+start(?:\s|$)/', $command);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $name) {
            $path = $dir . '/' . $name;
            is_dir($path) && !is_link($path) ? $this->removeDirectory($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    private function output(?string $message): void
    {
        if ($message === null || $message === '') {
            return;
        }

        match (true) {
            str_contains($message, '[INFO]') => SmartWatchLogger::info($message, false),
            str_contains($message, '[DEBUG]') => SmartWatchLogger::debug($message, false),
            str_contains($message, '[ERROR]') => SmartWatchLogger::error($message, false),
            str_contains($message, '[WARNING]') => SmartWatchLogger::warning($message, false),
            default => SmartWatchLogger::debug($message, false),
        };
    }
}

/**
 * 轻量文件轮询器。
 *
 * 只监听会影响后端运行时的 .env 与 PHP 源码，并排除 vendor/runtime/web/public/build 等目录；
 * 每轮重新收集文件集合，保证新增、删除和修改都会触发 Worker 重启。
 */
final class SmartWatchFileWatcher
{
    /**
     * @var array<string, string>
     */
    private array $hashes = [];

    private ?int $timerId = null;

    /**
     * @param string[] $excludeDirs
     */
    public function __construct(
        private readonly string $watchDir,
        private readonly string $watchExt,
        private readonly array $excludeDirs
    ) {}

    public function init(): static
    {
        $this->timerId === null || Timer::clear($this->timerId);
        $this->hashes = $this->collectHashes();
        SmartWatchLogger::info('🔄 Watching ' . count($this->hashes) . ' files...' . PHP_EOL);
        return $this;
    }

    public function watch(int $interval, callable $onChange): void
    {
        $this->timerId = Timer::tick($interval, function () use ($onChange): void {
            // 每轮重新扫描文件集合，确保新增、删除和修改 PHP/.env 文件都会触发开发 Worker 重启。
            if ($this->collectHashes() !== $this->hashes) {
                SmartWatchLogger::warning('📡 File change detected. Restarting...' . PHP_EOL);
                $onChange();
                $this->init();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    private function collectHashes(): array
    {
        $hashes = [];
        foreach ($this->getWatchedFiles() as $file) {
            $hashes[$file] = $this->getFileHash($file);
        }
        return $hashes;
    }

    /**
     * @return string[]
     */
    private function getWatchedFiles(): array
    {
        $files = [];
        $dirIt = new RecursiveDirectoryIterator($this->watchDir, FilesystemIterator::SKIP_DOTS);
        $filterIt = new RecursiveCallbackFilterIterator($dirIt, function (SplFileInfo $current): bool {
            $path = $current->getPathname();
            if ($current->isDir()) {
                return !$this->isExcluded($path . DIRECTORY_SEPARATOR . '_');
            }

            return !$this->isExcluded($path);
        });
        foreach (new RecursiveIteratorIterator($filterIt, RecursiveIteratorIterator::LEAVES_ONLY) as $fileInfo) {
            if ($this->isExcluded($fileInfo->getPathname())) {
                continue;
            }
            if (in_array(pathinfo($fileInfo->getFilename(), PATHINFO_EXTENSION), explode(',', $this->watchExt), true)) {
                $files[] = $fileInfo->getPathname();
            }
        }
        sort($files, SORT_STRING);
        return $files;
    }

    private function isExcluded(string $path): bool
    {
        $pathNorm = str_replace('\\', '/', $path);
        foreach ($this->excludeDirs as $exclude) {
            $seg = trim(str_replace('\\', '/', $exclude), '/');
            if ($seg !== '' && str_contains($pathNorm, '/' . $seg . '/')) {
                return true;
            }
        }
        return false;
    }

    private function getFileHash(string $path): string
    {
        // watch 只用于开发重启，使用 mtime + size 作为轻量指纹，避免每秒对全量源码做 md5 扫描。
        return file_exists($path) ? ((string)filemtime($path) . '-' . (string)filesize($path)) : 'deleted';
    }
}

final class SmartWatchLogger
{
    public static function info(string $message, bool $newLine = false): void
    {
        echo sprintf("\033[0;32m%s\033[0m", $message) . ($newLine ? PHP_EOL : '');
    }

    public static function debug(string $message, bool $newLine = false): void
    {
        echo sprintf("\033[0;34m%s\033[0m", $message) . ($newLine ? PHP_EOL : '');
    }

    public static function error(string $message, bool $newLine = false): void
    {
        echo sprintf("\033[0;31m%s\033[0m", $message) . ($newLine ? PHP_EOL : '');
    }

    public static function warning(string $message, bool $newLine = false): void
    {
        echo sprintf("\033[0;33m%s\033[0m", $message) . ($newLine ? PHP_EOL : '');
    }
}

exit((new SmartEntrypoint($_SERVER['argv'] ?? []))->run());
