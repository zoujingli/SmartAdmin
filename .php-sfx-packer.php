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
$argv = $_SERVER['argv'] ?? [];

try {
    [$mode, $source, $target] = parseSfxArguments($argv);
    switch ($mode) {
        case 'build':
            runReleaseBuild($target ?? '.');
            break;
        case 'precompile':
            runBuildPrecompile($target ?? '.');
            break;
        case 'audit':
            runBuildAudit($target ?? 'build/system');
            break;
        case 'pack':
            if ($source === null || $target === null) {
                throw new InvalidArgumentException(usageText());
            }
            packSfxTargets($source, $target);
            break;
        default:
            throw new InvalidArgumentException(usageText());
    }
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . PHP_EOL);
    exit(1);
}

/**
 * 解析命令参数；保留旧格式 `source target`，方便旧 Composer 脚本和人工命令继续使用。
 *
 * @return array{0: string, 1: ?string, 2: ?string}
 */
function parseSfxArguments(array $argv): array
{
    $command = $argv[1] ?? '';
    if ($command === 'build') {
        return ['build', null, $argv[2] ?? '.'];
    }
    if ($command === 'precompile') {
        return ['precompile', null, $argv[2] ?? '.'];
    }
    if ($command === 'audit') {
        return ['audit', null, $argv[2] ?? 'build/system'];
    }
    if ($command === 'pack') {
        return ['pack', $argv[2] ?? null, $argv[3] ?? null];
    }
    if (isset($argv[1], $argv[2])) {
        return ['pack', $argv[1], $argv[2]];
    }

    throw new InvalidArgumentException(usageText());
}

/**
 * 命令帮助文案。
 */
function usageText(): string
{
    return implode(PHP_EOL, [
        'Wrong arguments!',
        'Build example: ./.php-sfx-packer.php build',
        'Precompile example: ./.php-sfx-packer.php precompile .',
        'Pack example: ./.php-sfx-packer.php pack system.bin build/system',
        'Audit example: ./.php-sfx-packer.php audit build/system',
    ]);
}

/**
 * 发布构建总入口：收敛原 composer build 多段流程，并确保生产依赖安装后无论成功失败都恢复开发依赖。
 */
function runReleaseBuild(string $baseDir): void
{
    assertPhp84BuildRuntime();

    $baseDir = rtrim(str_replace('\\', '/', realpath($baseDir) ?: $baseDir), '/');
    if ($baseDir === '' || !is_file($baseDir . '/composer.json')) {
        throw new RuntimeException("构建目录无效或缺少 composer.json：{$baseDir}");
    }
    if (!chdir($baseDir)) {
        throw new RuntimeException("切换构建目录失败：{$baseDir}");
    }

    assertBundledSwooleRuntime();
    putenv('COMPOSER_ALLOW_SUPERUSER=1');

    $startedAt = microtime(true);
    $restoreDevDependencies = false;
    $buildFailure = null;
    $restoreFailure = null;

    try {
        assertFrontendDistReady();
        runBuildCommand('同步菜单种子', ['./bin/smart', 'xadmin:menu:sync', '--details']);
        runBuildCommand('同步权限节点', ['./bin/smart', 'xadmin:node:sync', '--details']);

        cleanReleaseWorkspace();
        runReleaseSnapshot();

        $restoreDevDependencies = true;
        runBuildCommand('安装生产依赖并启用权威 classmap', [
            './bin/smart',
            'composer',
            'install',
            '--no-dev',
            '--optimize-autoloader',
            '--classmap-authoritative',
            '--no-interaction',
            '--prefer-dist',
            '--no-progress',
        ]);

        runBuildCommand('预热 Hyperf 扫描与 DI 缓存', [
            './bin/smart',
            'runtime',
            '-d',
            'opcache.enable_cli=0',
            './bin/hyperf.php',
            'list',
            '--no-ansi',
            '--no-interaction',
        ], true);

        runBuildPrecompile('.');

        runBuildCommand('生成 Phar：system.bin', [
            './bin/smart',
            'runtime',
            '-d',
            'phar.readonly=Off',
            '-d',
            'opcache.enable=0',
            './bin/hyperf.php',
            'xadmin:build:phar',
            '--mount=.env',
            '--name=system.bin',
            '--phar-version=2.0.0',
        ], false, [
            'APP_ENV' => 'prod',
            'SCAN_CACHEABLE' => 'true',
        ]);

        echo '[build] 生成多架构 SFX 包' . PHP_EOL;
        packSfxTargets('system.bin', 'build/system');
        cleanReleaseArtifacts();
        echo '[build] 审计发布包' . PHP_EOL;
        runBuildAudit('build/system');
    } catch (Throwable $throwable) {
        $buildFailure = $throwable;
    } finally {
        if ($restoreDevDependencies) {
            try {
                runBuildCommand('恢复本地开发依赖', [
                    './bin/smart',
                    'composer',
                    'install',
                    '--optimize-autoloader',
                    '--no-interaction',
                    '--prefer-dist',
                    '--no-progress',
                ]);
            } catch (Throwable $throwable) {
                $restoreFailure = $throwable;
            }
        }
    }

    if ($buildFailure !== null || $restoreFailure !== null) {
        $messages = [];
        if ($buildFailure !== null) {
            $messages[] = $buildFailure->getMessage();
        }
        if ($restoreFailure !== null) {
            $messages[] = '恢复本地开发依赖失败：' . $restoreFailure->getMessage();
        }
        throw new RuntimeException(implode(PHP_EOL, $messages), 0, $buildFailure ?? $restoreFailure);
    }

    echo sprintf('[build] OK elapsed=%ss', round(microtime(true) - $startedAt, 1)) . PHP_EOL;
}

/**
 * 发布构建直接复用现有 web/dist，避免 composer build 隐式触发前端编译。
 *
 * 前端产物应由独立流水线或人工执行 composer web:build 生成；这里仅校验入口文件存在且非空，
 * 防止误用旧产物清理后的空目录继续打包出不可访问的发布包。
 */
function assertFrontendDistReady(): void
{
    $index = 'web/dist/index.html';
    if (!is_file($index) || filesize($index) <= 0) {
        throw new RuntimeException('构建失败：缺少可打包的前端产物 web/dist/index.html，请先生成或放置 web/dist');
    }

    echo '[build] 复用现有前端产物 web/dist' . PHP_EOL;
}

/**
 * 执行构建子命令，统一输出阶段名；可为 Hyperf 构建命令注入 APP_ENV/SCAN_CACHEABLE 等临时变量。
 *
 * @param string[] $command
 * @param array<string,string> $env
 */
function runBuildCommand(string $title, array $command, bool $discardStdout = false, array $env = []): void
{
    echo "[build] {$title}" . PHP_EOL;
    $parts = [];
    foreach ($env as $name => $value) {
        $parts[] = $name . '=' . escapeshellarg($value);
    }
    foreach ($command as $argument) {
        $parts[] = escapeshellarg($argument);
    }
    $line = implode(' ', $parts);
    if ($discardStdout) {
        $line .= ' > ' . (DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null');
    }

    $exitCode = 0;
    passthru($line, $exitCode);
    if ($exitCode !== 0) {
        throw new RuntimeException("构建命令失败({$exitCode})：{$title}");
    }
}

/**
 * 构建前生成 Phar 内安装包；安装包只包含完整结构与 release 必要数据，不携带运行期全量数据。
 */
function runReleaseSnapshot(): void
{
    runBuildCommand('生成数据库安装包', ['./bin/smart', 'xadmin:release:backup', '--install']);
    runBuildCommand('预览安装包恢复 SQL', ['./bin/smart', 'xadmin:release:restore', '--install', '--dry-run', '--json']);

    foreach (['database.schema.gz', 'database.data.gz', 'database.meta.json'] as $filename) {
        $source = 'storage/extra/release/' . $filename;
        if (!is_file($source) || filesize($source) === 0) {
            throw new RuntimeException("数据库安装包缺失或为空：{$source}");
        }
    }
}

/**
 * 清理发布工作区；只删除明确构建产物和容器预编译缓存，不删除源码 public/runtime、vendor 或 composer.lock。
 */
function cleanReleaseWorkspace(): void
{
    removePath('build');
    removePath('storage/extra/release');
    removePaths([
        'system.bin',
        'runtime/container',
    ]);
}

/**
 * 清理 Phar 中间文件并放置部署模板；运行期 .env 必须由部署环境自行提供。
 */
function cleanReleaseArtifacts(): void
{
    removePath('system.bin');
    is_dir('build') || mkdir('build', 0755, true);
    if (is_file('.env.example')) {
        copyFileAtomic('.env.example', 'build/.env.example');
    }
    removePaths(['build/.env', 'build/.DS_Store', 'build/public']);
}

/**
 * 构建期预编译：生成 Hyperf 扫描缓存并写入构建清单，确保 Phar 启动不退回动态扫描。
 */
function runBuildPrecompile(string $baseDir): void
{
    assertPhp84BuildRuntime();

    $baseDir = rtrim(str_replace('\\', '/', realpath($baseDir) ?: $baseDir), '/') . '/';
    if (!is_file($baseDir . 'composer.json')) {
        throw new RuntimeException('构建预编译失败：缺少 composer.json');
    }
    if (!chdir($baseDir)) {
        throw new RuntimeException("切换构建目录失败：{$baseDir}");
    }
    assertBundledSwooleRuntime();

    $autoload = $baseDir . 'vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('构建预编译失败：缺少 vendor/autoload.php，请先执行 composer install --no-dev');
    }

    $loader = require $autoload;
    if (!is_object($loader) || !method_exists($loader, 'isClassMapAuthoritative') || !$loader->isClassMapAuthoritative()) {
        throw new RuntimeException('构建预编译失败：Composer 未启用 --classmap-authoritative');
    }

    $installedPackages = readInstalledPackages($baseDir);
    $devPackages = ['friendsofphp/php-cs-fixer', 'mockery/mockery', 'phpstan/phpstan', 'phpunit/phpunit', 'swoole/ide-helper'];
    $installedNames = array_map(static fn (array $package): string => strtolower((string)($package['name'] ?? '')), $installedPackages);
    foreach ($devPackages as $package) {
        if (in_array($package, $installedNames, true)) {
            throw new RuntimeException("构建预编译失败：生产依赖中仍包含开发包 {$package}");
        }
    }

    $runtimeDir = $baseDir . 'runtime/container';
    removePath($runtimeDir);
    is_dir($runtimeDir) || mkdir($runtimeDir, 0755, true);

    runBuildCommand('生成 Hyperf 扫描缓存', ['./bin/smart', 'list', '--no-ansi', '--no-interaction'], true, [
        'APP_ENV' => 'prod',
        'SCAN_CACHEABLE' => 'true',
    ]);

    $requiredCaches = ['scan.cache', 'classes.cache', 'aspects.cache'];
    $cacheManifest = [];
    foreach ($requiredCaches as $cache) {
        $path = $runtimeDir . '/' . $cache;
        if (!is_file($path) || filesize($path) === 0) {
            throw new RuntimeException("构建预编译失败：Hyperf 缓存未生成 runtime/container/{$cache}");
        }
        $cacheManifest[$cache] = [
            'size' => filesize($path),
            'sha256' => hash_file('sha256', $path),
        ];
    }

    $proxyDir = $runtimeDir . '/proxy';
    $proxyCount = is_dir($proxyDir) ? count(glob($proxyDir . '/*.php') ?: []) : 0;
    if ($proxyCount <= 0) {
        throw new RuntimeException('构建预编译失败：DI 代理目录为空 runtime/container/proxy');
    }

    $manifest = [
        'schema' => 1,
        'generated_at' => date(DATE_ATOM),
        'php_version' => PHP_VERSION,
        'swoole_version' => runtimeVersionValue('swoole_version'),
        'classmap_authoritative' => true,
        'scan_cacheable' => true,
        'runtime_caches' => $cacheManifest,
        'proxy_count' => $proxyCount,
        // 构建指纹只记录稳定文件树摘要，便于定位发布包与源码、依赖、前端产物是否匹配。
        'composer_lock' => hashFileIfExists('composer.lock'),
        'plugin_lock' => hashFileIfExists('plugin.lock.json'),
        'config_autoload' => hashTree('config/autoload'),
        'plugin_tree' => hashTree('plugin'),
        'web_dist' => hashTree('web/dist'),
        'release_install_package' => hashTree('storage/extra/release'),
        'packages' => array_values(array_map(static fn (array $package): array => [
            'name' => (string)($package['name'] ?? ''),
            'version' => (string)($package['version'] ?? ''),
        ], $installedPackages)),
    ];

    file_put_contents(
        $runtimeDir . '/build.manifest.json',
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL
    );

    echo sprintf('[build-precompile] OK classmap=authoritative scan.cache=yes proxies=%d', $proxyCount) . PHP_EOL;
}

/**
 * 按交付矩阵生成自解压可执行包，二进制追加 Phar 后写入 Phar 长度供 SFX `--self` 读取。
 */
function packSfxTargets(string $source, string $target): void
{
    assertPhp84BuildRuntime();
    assertBundledSwooleRuntime();

    if (!is_file($source)) {
        throw new RuntimeException("Phar source not found: {$source}");
    }

    is_dir(dirname($target)) || mkdir(dirname($target), 0755, true);

    buildSfxBinary('./bin/swoole-linux-x64', $source, $target . '-linux-x64');
    buildSfxBinary('./bin/swoole-linux-a64', $source, $target . '-linux-a64');
    buildSfxBinary('./bin/swoole-macos-a64', $source, $target . '-macos-a64');
    copyOrLinkFile($target . '-linux-x64', $target);
}

/**
 * 打包脚本本身必须运行在 PHP 8.4+，避免生成低版本不兼容 classmap 或缓存。
 */
function assertPhp84BuildRuntime(): void
{
    if (PHP_VERSION_ID < 80400) {
        throw new RuntimeException('构建失败：composer build 必须使用 PHP 8.4 及以上运行时，当前为 ' . PHP_VERSION);
    }
}

/**
 * 校验项目内置 Swoole CLI 能力，确保精简基库仍覆盖 SmartAdmin 的 Phar 运行边界。
 */
function assertBundledSwooleRuntime(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    foreach (['bin/swoole-linux-x64', 'bin/swoole-linux-a64', 'bin/swoole-macos-a64'] as $binary) {
        if (!is_file($binary) || filesize($binary) === 0) {
            throw new RuntimeException("构建失败：缺少 Swoole 基库 {$binary}");
        }
    }

    $code = <<<'PHP_CODE'
$required = array_values(array_filter(explode(',', getenv('XADMIN_REQUIRED_EXTENSIONS') ?: '')));
$forbidden = array_values(array_filter(explode(',', getenv('XADMIN_FORBIDDEN_EXTENSIONS') ?: '')));
$errors = [];
if (PHP_VERSION_ID < 80400) {
    $errors[] = 'PHP_VERSION must be >= 8.4, current=' . PHP_VERSION;
}
if (!defined('SWOOLE_CLI')) {
    $errors[] = 'SWOOLE_CLI constant is not defined';
}
if (!defined('SWOOLE_VERSION') || version_compare(SWOOLE_VERSION, '6.2.0', '<')) {
    $errors[] = 'Swoole version should be >= 6.2.0';
}
foreach ($required as $extension) {
    if (!extension_loaded($extension)) {
        $errors[] = 'Missing extension: ' . $extension;
    }
}
foreach ($forbidden as $extension) {
    if (extension_loaded($extension)) {
        $errors[] = 'Unexpected extension: ' . $extension;
    }
}
echo json_encode([
    'php_version' => PHP_VERSION,
    'swoole_version' => defined('SWOOLE_VERSION') ? SWOOLE_VERSION : null,
    'errors' => $errors,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($errors === [] ? 0 : 1);
PHP_CODE;

    $env = [
        'XADMIN_REQUIRED_EXTENSIONS' => implode(',', requiredRuntimeExtensions()),
        'XADMIN_FORBIDDEN_EXTENSIONS' => implode(',', forbiddenRuntimeExtensions()),
    ];
    $parts = [];
    foreach ($env as $name => $value) {
        $parts[] = $name . '=' . escapeshellarg($value);
    }
    foreach (['./bin/smart', 'runtime', '-r', $code] as $argument) {
        $parts[] = escapeshellarg($argument);
    }

    $output = [];
    $status = 0;
    exec(implode(' ', $parts), $output, $status);
    $message = implode(PHP_EOL, $output);
    if ($status !== 0) {
        throw new RuntimeException('构建失败：Swoole 基库校验未通过 ' . $message);
    }

    $checked = true;
}

/**
 * @return string[]
 */
function requiredRuntimeExtensions(): array
{
    return [
        'bcmath',
        'bz2',
        'curl',
        'dom',
        'fileinfo',
        'gd',
        'json',
        'mbstring',
        'opcache',
        'openssl',
        'pcntl',
        'pdo',
        'pdo_mysql',
        // SQLite 是免 MySQL 体验环境的默认数据库能力，运行时构建校验必须强制保留。
        'pdo_sqlite',
        'phar',
        'posix',
        'redis',
        'simplexml',
        'sockets',
        'sodium',
        'sqlite3',
        'tokenizer',
        'xml',
        'xmlreader',
        'xmlwriter',
        'zip',
        'zlib',
    ];
}

/**
 * @return string[]
 */
function forbiddenRuntimeExtensions(): array
{
    return [
        'exif',
        'gettext',
        'gmp',
        'imagick',
        'intl',
        'mongodb',
        'mysqli',
        'readline',
        'session',
        'soap',
        'xlswriter',
        'xsl',
        'yaml',
    ];
}

/**
 * 优先硬链接复用默认 Linux x64 交付入口；不支持硬链接时退回复制。
 */
function copyOrLinkFile(string $source, string $target): void
{
    if (is_file($target) && !unlink($target)) {
        throw new RuntimeException("Remove old target {$target} failed!");
    }
    if (!@link($source, $target) && !copy($source, $target)) {
        throw new RuntimeException("Copy file {$source} to {$target} failed!");
    }
    chmod($target, 0755);
}

/**
 * 原子替换目标文件，避免部署脚本读取到半写入产物。
 */
function copyFileAtomic(string $source, string $target): void
{
    is_dir(dirname($target)) || mkdir(dirname($target), 0755, true);
    $tmp = $target . '.tmp.' . getmypid();
    if (!copy($source, $tmp)) {
        throw new RuntimeException("Copy file {$source} to {$tmp} failed!");
    }
    if (!rename($tmp, $target)) {
        @unlink($tmp);
        throw new RuntimeException("Rename file {$tmp} to {$target} failed!");
    }
}

/**
 * 生成单个 SFX 文件；用流复制替代 shell `cat`，避免路径转义和注入风险。
 */
function buildSfxBinary(string $binary, string $source, string $target): void
{
    if (!is_file($binary)) {
        throw new RuntimeException("Swoole binary not found: {$binary}");
    }
    if (!copy($binary, $target)) {
        throw new RuntimeException("Copy file {$binary} to {$target} failed!");
    }
    chmod($target, 0755);

    $sourceSize = filesize($source);
    if ($sourceSize === false || $sourceSize <= 0) {
        throw new RuntimeException("Get file {$source} size failed!");
    }

    $input = fopen($source, 'rb');
    $output = fopen($target, 'ab');
    if ($input === false || $output === false) {
        is_resource($input) && fclose($input);
        is_resource($output) && fclose($output);
        throw new RuntimeException("Open SFX source or target failed: {$source} -> {$target}");
    }

    try {
        if (stream_copy_to_stream($input, $output) === false) {
            throw new RuntimeException("Append Phar {$source} to {$target} failed!");
        }
        if (fwrite($output, pack('J', $sourceSize)) === false) {
            throw new RuntimeException("Write Phar size tail to {$target} failed!");
        }
    } finally {
        fclose($input);
        fclose($output);
    }

    echo sprintf('[pack] %s size=%s KiB', $target, round(filesize($target) / 1024, 1)) . PHP_EOL;
}

/**
 * 发布包审计：检查目录边界、Phar 内容、预编译状态、前端资源包和源码明文残留。
 */
function runBuildAudit(string $target): void
{
    assertPhp84BuildRuntime();
    assertBundledSwooleRuntime();

    $errors = [];
    if (!is_file($target)) {
        $errors[] = "发布包不存在：{$target}";
    } else {
        $errors = array_merge($errors, auditBuildDirectory(dirname($target)));
        try {
            $pharFile = extractPharForAudit($target);
            $errors = array_merge($errors, auditPharEntries($pharFile));
            $errors = array_merge($errors, auditPharPrecompileState($pharFile));
            $errors = array_merge($errors, auditFrontendArchive($pharFile));
            $errors = array_merge($errors, auditReleaseInstallPackage($pharFile));
            $errors = array_merge($errors, auditBinaryClearText($target));
            $errors = array_merge($errors, auditSfxTargets($target));
        } catch (Throwable $throwable) {
            $errors[] = $throwable->getMessage();
        } finally {
            if (isset($pharFile) && $pharFile !== $target && is_file($pharFile)) {
                @unlink($pharFile);
            }
        }
    }

    if ($errors !== []) {
        foreach ($errors as $error) {
            fwrite(STDERR, "[build-audit] {$error}" . PHP_EOL);
        }
        throw new RuntimeException('[build-audit] FAILED');
    }

    echo '[build-audit] OK' . PHP_EOL;
}

/**
 * @return string[]
 */
function auditBuildDirectory(string $buildDir): array
{
    $errors = [];
    if (is_file($buildDir . '/.env')) {
        $errors[] = '发布目录不能包含真实 .env，请仅交付 .env.example 模板';
    }
    if (is_file($buildDir . '/.DS_Store')) {
        $errors[] = '发布目录不能包含 macOS .DS_Store 辅助文件';
    }
    if (is_dir($buildDir . '/public')) {
        $errors[] = '发布目录不再携带 public 静态资源，请使用 Phar 内 storage/extra/web-dist.zip 按需发布';
    }

    if (is_dir($buildDir . '/runtime')) {
        $errors[] = '发布目录不再携带 runtime 快照，数据库安装包必须位于 Phar 内 storage/extra/release';
    }

    return $errors;
}

/**
 * 提取追加在 Swoole CLI 二进制尾部的 Phar；审计使用只读临时副本，不修改发布包。
 */
function extractPharForAudit(string $target): string
{
    $prefix = file_get_contents($target, false, null, 0, 32);
    if (is_string($prefix) && (str_starts_with($prefix, '#!/usr/bin/env php') || str_starts_with($prefix, '<?php'))) {
        return $target;
    }

    $totalSize = filesize($target);
    if ($totalSize === false || $totalSize <= 8) {
        throw new RuntimeException("发布包大小异常：{$target}");
    }

    $source = fopen($target, 'rb');
    if ($source === false) {
        throw new RuntimeException("无法读取发布包：{$target}");
    }

    fseek($source, -8, SEEK_END);
    $tail = fread($source, 8);
    if (!is_string($tail) || strlen($tail) !== 8) {
        fclose($source);
        throw new RuntimeException("无法读取发布包尾部 Phar 大小：{$target}");
    }

    $pharSize = unpack('J', $tail)[1] ?? 0;
    if (!is_int($pharSize) || $pharSize <= 0 || $pharSize >= $totalSize) {
        fclose($source);
        throw new RuntimeException("发布包尾部 Phar 大小无效：{$target}");
    }

    $offset = $totalSize - $pharSize - 8;
    if ($offset < 0) {
        fclose($source);
        throw new RuntimeException("发布包尾部 Phar 偏移无效：{$target}");
    }

    $tmp = tempnam(sys_get_temp_dir(), 'xadmin-audit-');
    if ($tmp === false) {
        fclose($source);
        throw new RuntimeException('无法创建发布包审计临时文件');
    }
    $pharFile = $tmp . '.phar';
    @unlink($tmp);

    $targetStream = fopen($pharFile, 'wb');
    if ($targetStream === false) {
        fclose($source);
        throw new RuntimeException("无法写入发布包审计临时文件：{$pharFile}");
    }

    fseek($source, $offset);
    stream_copy_to_stream($source, $targetStream, $pharSize);
    fclose($targetStream);
    fclose($source);

    return $pharFile;
}

/**
 * 检查 Phar 内部是否混入敏感配置、源码辅助文件、测试目录或 raw 前端目录。
 *
 * @return string[]
 */
function auditPharEntries(string $pharFile): array
{
    if (!class_exists(Phar::class)) {
        return ['当前 PHP 环境不支持 Phar 审计'];
    }

    $errors = [];
    $phar = new Phar($pharFile);
    $base = 'phar://' . str_replace('\\', '/', realpath($pharFile) ?: $pharFile) . '/';
    $forbiddenExact = [
        '.env',
        '.php-cs-fixer.php',
        '.php-sfx-packer.php',
        'phpstan.neon',
        'phpunit.xml',
    ];
    $forbiddenPrefixes = [
        '.git/',
        '.github/',
        'build/',
        'devtools/',
        'docs/',
        'public/',
        'tests/',
        'web/',
        'vendor/friendsofphp/',
        'vendor/mockery/',
        'vendor/phpstan/',
        'vendor/phpunit/',
        'vendor/swoole/ide-helper/',
    ];

    foreach (new RecursiveIteratorIterator($phar) as $fileInfo) {
        /** @var SplFileInfo $fileInfo */
        $path = str_replace('\\', '/', $fileInfo->getPathname());
        $local = str_starts_with($path, $base) ? substr($path, strlen($base)) : ltrim($path, '/');
        $basename = basename($local);

        if (in_array($local, $forbiddenExact, true) || $basename === '.DS_Store') {
            $errors[] = "Phar 内部包含禁止文件：{$local}";
            continue;
        }

        foreach ($forbiddenPrefixes as $prefix) {
            if (str_starts_with($local, $prefix)) {
                $errors[] = "Phar 内部包含禁止目录：{$local}";
                break;
            }
        }
    }

    return $errors;
}

/**
 * 检查 Composer/Hyperf 预编译产物是否进入 Phar。
 *
 * @return string[]
 */
function auditPharPrecompileState(string $pharFile): array
{
    $phar = new Phar($pharFile);
    $errors = [];
    $required = [
        'xadmin_obfuscate.php',
        'runtime/container/scan.cache',
        'runtime/container/classes.cache',
        'runtime/container/aspects.cache',
        'runtime/container/build.manifest.json',
        'vendor/composer/autoload_real.php',
        'vendor/composer/autoload_classmap.php',
        'storage/extra/web-dist.zip',
        'storage/extra/release/database.schema.gz',
        'storage/extra/release/database.data.gz',
        'storage/extra/release/database.meta.json',
    ];
    foreach ($required as $path) {
        if (!isset($phar[$path]) || $phar[$path]->getSize() <= 0) {
            $errors[] = "Phar 缺少预编译产物：{$path}";
        }
    }

    if (isset($phar['vendor/composer/autoload_real.php'])) {
        $autoloadReal = $phar['vendor/composer/autoload_real.php']->getContent();
        if (!str_contains($autoloadReal, 'setClassMapAuthoritative(true)')) {
            $errors[] = 'Composer autoload 未启用 classmap authoritative';
        }
    }

    if (isset($phar['runtime/container/build.manifest.json'])) {
        $manifest = json_decode($phar['runtime/container/build.manifest.json']->getContent(), true);
        if (!is_array($manifest) || ($manifest['classmap_authoritative'] ?? false) !== true || ($manifest['scan_cacheable'] ?? false) !== true) {
            $errors[] = '构建清单未标记 classmap_authoritative/scan_cacheable';
        }
        if ((int)($manifest['proxy_count'] ?? 0) <= 0) {
            $errors[] = '构建清单 proxy_count 异常';
        }
    }

    return $errors;
}

/**
 * 审计 Phar 内数据库安装包：安装包只能包含结构与必要数据，不能携带 --with-data 全量运行数据。
 *
 * @return string[]
 */
function auditReleaseInstallPackage(string $pharFile): array
{
    $phar = new Phar($pharFile);
    $required = [
        'storage/extra/release/database.schema.gz',
        'storage/extra/release/database.data.gz',
        'storage/extra/release/database.meta.json',
    ];
    $errors = [];
    foreach ($required as $path) {
        if (!isset($phar[$path]) || $phar[$path]->getSize() <= 0) {
            $errors[] = "Phar 缺少数据库安装包：{$path}";
        }
    }

    if (isset($phar['storage/extra/release/database.meta.json'])) {
        $meta = json_decode($phar['storage/extra/release/database.meta.json']->getContent(), true);
        if (!is_array($meta) || ($meta['kind'] ?? null) !== 'install' || ($meta['with_data'] ?? true) !== false) {
            $errors[] = '数据库安装包元数据非法，必须 kind=install 且 with_data=false';
        }
    }

    return $errors;
}

/**
 * 审计 Phar 内 web-dist.zip：必须包含 index.html，并排除动态配置、本机元数据和不安全路径。
 *
 * @return string[]
 */
function auditFrontendArchive(string $pharFile): array
{
    $phar = new Phar($pharFile);
    $path = 'storage/extra/web-dist.zip';
    if (!isset($phar[$path]) || $phar[$path]->getSize() <= 0) {
        return ["Phar 缺少前端资源包：{$path}"];
    }

    $tmp = tempnam(sys_get_temp_dir(), 'xadmin-web-dist-audit-');
    if ($tmp === false) {
        return ['无法创建前端资源包审计临时文件'];
    }

    $errors = [];
    try {
        file_put_contents($tmp, $phar[$path]->getContent(), LOCK_EX);
        $zip = new ZipArchive();
        if ($zip->open($tmp) !== true) {
            return ['无法打开 Phar 内前端资源包：storage/extra/web-dist.zip'];
        }

        $hasIndex = false;
        try {
            for ($index = 0; $index < $zip->numFiles; ++$index) {
                $name = $zip->getNameIndex($index);
                if (!is_string($name)) {
                    continue;
                }
                $raw = str_replace('\\', '/', $name);
                $relative = trim($raw, '/');
                if ($relative === '' || str_ends_with($raw, '/')) {
                    continue;
                }
                if (str_starts_with($raw, '/') || preg_match('#^[A-Za-z]:/#', $raw) === 1 || preg_match('#(^|/)\.\.(/|$)#', $relative) === 1) {
                    $errors[] = "前端资源包包含非法路径：{$name}";
                    continue;
                }
                if (basename($relative) === '.DS_Store') {
                    $errors[] = "前端资源包包含 macOS .DS_Store：{$relative}";
                }
                if ($relative === '_app.config.js') {
                    $errors[] = '前端资源包不能包含动态配置：_app.config.js';
                }
                if ($relative === 'index.html') {
                    $hasIndex = true;
                }
            }
        } finally {
            $zip->close();
        }

        if (!$hasIndex) {
            $errors[] = '前端资源包缺少入口页：index.html';
        }
    } finally {
        @unlink($tmp);
    }

    return $errors;
}

/**
 * 检查发布包矩阵是否完整，默认 build/system 必须指向 Linux x64 产物。
 *
 * @return string[]
 */
function auditSfxTargets(string $target): array
{
    $base = preg_replace('/-(linux-x64|linux-a64|macos-a64)$/', '', $target);
    if (!is_string($base) || $base === '') {
        $base = $target;
    }

    $errors = [];
    foreach (['linux-x64', 'linux-a64', 'macos-a64'] as $suffix) {
        $file = "{$base}-{$suffix}";
        if (!is_file($file) || filesize($file) <= 0) {
            $errors[] = "缺少架构发布包：{$file}";
        }
    }
    if (is_file($base) && is_file($base . '-linux-x64') && hash_file('sha256', $base) !== hash_file('sha256', $base . '-linux-x64')) {
        $errors[] = '默认发布入口 build/system 必须与 Linux x64 产物一致';
    }

    return $errors;
}

/**
 * 检查发布包原始二进制中是否仍能直接看到一方源码注释片段。
 *
 * @return string[]
 */
function auditBinaryClearText(string $target): array
{
    $markers = [
        'This file is part of SmartAdmin',
        '@contact Anyon',
        'zoujingli.github.io/SmartAdmin',
    ];
    $errors = [];
    foreach ($markers as $marker) {
        if (binaryContains($target, $marker)) {
            $errors[] = "发布包仍包含可直接检索的一方源码片段：{$marker}";
        }
    }
    return $errors;
}

/**
 * 分块检索二进制内容，避免一次性读取大文件。
 */
function binaryContains(string $filename, string $needle): bool
{
    $stream = fopen($filename, 'rb');
    if ($stream === false) {
        throw new RuntimeException("无法读取发布包：{$filename}");
    }

    $overlap = '';
    $needleLength = strlen($needle);
    while (!feof($stream)) {
        $chunk = fread($stream, 1024 * 1024);
        if (!is_string($chunk)) {
            break;
        }
        if (str_contains($overlap . $chunk, $needle)) {
            fclose($stream);
            return true;
        }
        $overlap = substr($chunk, -max(0, $needleLength - 1));
    }

    fclose($stream);
    return false;
}

/**
 * 批量删除构建产物路径。
 *
 * @param string[] $paths
 */
function removePaths(array $paths): void
{
    foreach ($paths as $path) {
        removePath($path);
    }
}

/**
 * 递归删除文件、软链或目录；仅用于明确列出的构建产物路径。
 */
function removePath(string $path): void
{
    if (!file_exists($path) && !is_link($path)) {
        return;
    }
    if (is_file($path) || is_link($path)) {
        if (!unlink($path)) {
            throw new RuntimeException("删除文件失败：{$path}");
        }
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $fileInfo) {
        /** @var SplFileInfo $fileInfo */
        $realPath = $fileInfo->getPathname();
        if ($fileInfo->isDir() && !$fileInfo->isLink()) {
            if (!rmdir($realPath)) {
                throw new RuntimeException("删除目录失败：{$realPath}");
            }
        } elseif (!unlink($realPath)) {
            throw new RuntimeException("删除文件失败：{$realPath}");
        }
    }
    if (!rmdir($path)) {
        throw new RuntimeException("删除目录失败：{$path}");
    }
}

/**
 * 读取 Composer installed.json，兼容 Composer 1/2 结构。
 *
 * @return array<int, array<string, mixed>>
 */
function readInstalledPackages(string $baseDir): array
{
    $installedFile = rtrim($baseDir, '/') . '/vendor/composer/installed.json';
    if (!is_file($installedFile)) {
        throw new RuntimeException('构建预编译失败：缺少 vendor/composer/installed.json');
    }
    $installed = json_decode((string)file_get_contents($installedFile), true, 512, JSON_THROW_ON_ERROR);
    $packages = $installed['packages'] ?? $installed;
    return is_array($packages) ? array_values(array_filter($packages, 'is_array')) : [];
}

/**
 * 读取当前 Swoole CLI 的版本信息字段。
 */
function runtimeVersionValue(string $key): string
{
    $code = "echo json_encode(['php_version'=>PHP_VERSION,'swoole_version'=>defined('SWOOLE_VERSION') ? SWOOLE_VERSION : ''], JSON_UNESCAPED_SLASHES);";
    $output = [];
    $status = 0;
    exec(escapeshellarg('./bin/smart') . ' ' . escapeshellarg('runtime') . ' -r ' . escapeshellarg($code), $output, $status);
    if ($status !== 0) {
        return '';
    }
    $payload = json_decode(implode('', $output), true);
    return is_array($payload) ? (string)($payload[$key] ?? '') : '';
}

function hashFileIfExists(string $path): string
{
    return is_file($path) ? (hash_file('sha256', $path) ?: '') : '';
}

/**
 * 生成目录内容摘要；跳过本机元数据和 node_modules，避免构建清单受无关缓存影响。
 */
function hashTree(string $dir): string
{
    if (!is_dir($dir)) {
        return '';
    }

    $base = rtrim(str_replace('\\', '/', getcwd() ?: '.'), '/') . '/';
    $items = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $fileInfo) {
        /** @var SplFileInfo $fileInfo */
        if (!$fileInfo->isFile()) {
            continue;
        }
        if (in_array($fileInfo->getFilename(), ['.DS_Store', 'Thumbs.db'], true)) {
            continue;
        }
        $path = str_replace('\\', '/', $fileInfo->getPathname());
        $relative = str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
        if (str_contains($relative, '/.git/') || str_contains($relative, '/node_modules/')) {
            continue;
        }
        $items[$relative] = hash_file('sha256', $path);
    }
    ksort($items);

    return hash('sha256', json_encode($items, JSON_UNESCAPED_SLASHES));
}
