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
        case 'snapshot':
            runStandaloneReleaseSnapshot($target ?? '.', false);
            break;
        case 'snapshot-isolated':
            runStandaloneReleaseSnapshot($target ?? '.', true);
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
 * 解析标准子命令参数。
 *
 * @return array{0: string, 1: ?string, 2: ?string}
 */
function parseSfxArguments(array $argv): array
{
    $command = $argv[1] ?? '';
    if ($command === 'build') {
        return ['build', null, $argv[2] ?? '.'];
    }
    if ($command === 'snapshot') {
        return ['snapshot', null, $argv[2] ?? '.'];
    }
    if ($command === 'snapshot-isolated') {
        return ['snapshot-isolated', null, $argv[2] ?? '.'];
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
        'Snapshot example: ./.php-sfx-packer.php snapshot .',
        'Strict snapshot example: ./.php-sfx-packer.php snapshot-isolated .',
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

    // 只要已确认是合法源码目录，新构建就必须立即作废上一版安装包。
    // 后续即使在运行时、前端产物或任何快照前置检查失败，也不得留下可被误打包的旧 final/staging。
    invalidateReleaseInstallArtifacts();
    assertBundledSwooleRuntime();
    putenv('COMPOSER_ALLOW_SUPERUSER=1');

    $startedAt = microtime(true);
    $restoreDevDependencies = false;
    $buildFailure = null;
    $restoreFailure = null;

    try {
        assertFrontendDistReady();
        cleanReleaseWorkspace();
        runReleaseSnapshot(false);

        $restoreDevDependencies = true;
        runBuildCommand('安装生产依赖并启用权威 classmap', [
            './bin/smart.php',
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
            './bin/smart.php',
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
            './bin/smart.php',
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
                    './bin/smart.php',
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
 * 独立安装快照入口。日常构建读取当前配置库，严格检查才使用 fresh 双库隔离。
 */
function runStandaloneReleaseSnapshot(string $baseDir, bool $strictIsolation): void
{
    assertPhp84BuildRuntime();

    $baseDir = rtrim(str_replace('\\', '/', realpath($baseDir) ?: $baseDir), '/');
    if ($baseDir === '' || !is_file($baseDir . '/composer.json')) {
        throw new RuntimeException("快照目录无效或缺少 composer.json：{$baseDir}");
    }
    if (!chdir($baseDir)) {
        throw new RuntimeException("切换快照目录失败：{$baseDir}");
    }

    assertBundledSwooleRuntime();
    runReleaseSnapshot($strictIsolation);
}

/**
 * 发布构建直接复用现有 web/dist，避免 composer build 隐式触发前端编译。
 *
 * 前端产物应由独立流水线或人工执行 composer web:build 生成；这里校验入口与 static 资源存在，
 * 防止误用产物清理后的空目录继续打包出不可访问的发布包。
 */
function assertFrontendDistReady(): void
{
    $index = 'web/dist/index.html';
    if (!is_file($index) || filesize($index) <= 0) {
        throw new RuntimeException('构建失败：缺少可打包的前端产物 web/dist/index.html，请先生成或放置 web/dist');
    }
    if (!hasFrontendStaticFile('web/dist/static')) {
        throw new RuntimeException('构建失败：缺少可打包的前端静态资源 web/dist/static，请先执行 composer web:build');
    }

    echo '[build] 复用现有前端产物 web/dist' . PHP_EOL;
}

function hasFrontendStaticFile(string $path): bool
{
    if (!is_dir($path)) {
        return false;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($iterator as $fileInfo) {
        if ($fileInfo instanceof SplFileInfo && $fileInfo->isFile()) {
            return true;
        }
    }

    return false;
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
    $line = buildShellCommand($command, $env);
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
 * 运行必须返回单一 JSON 对象的构建命令；发布恢复验证依赖结构化报告，不能只看退出码。
 *
 * @param string[] $command
 * @param array<string,string> $env
 * @return array<string,mixed>
 */
function runBuildJsonCommand(string $title, array $command, array $env = []): array
{
    echo "[build] {$title}" . PHP_EOL;
    $output = [];
    $exitCode = 0;
    exec(buildShellCommand($command, $env) . ' 2>&1', $output, $exitCode);
    $payload = implode(PHP_EOL, $output);
    if ($payload !== '') {
        echo $payload . PHP_EOL;
    }
    if ($exitCode !== 0) {
        throw new RuntimeException("构建命令失败({$exitCode})：{$title}");
    }

    try {
        $report = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new RuntimeException("构建命令未返回合法 JSON：{$title}", 0, $exception);
    }
    if (!is_array($report)) {
        throw new RuntimeException("构建命令未返回 JSON 对象：{$title}");
    }

    return $report;
}

/**
 * @param string[] $command
 * @param array<string,string> $env
 */
function buildShellCommand(array $command, array $env = []): string
{
    $parts = [];
    foreach ($env as $name => $value) {
        $parts[] = $name . '=' . escapeshellarg($value);
    }
    foreach ($command as $argument) {
        $parts[] = escapeshellarg($argument);
    }

    return implode(' ', $parts);
}

/**
 * 构建前生成 Phar 内安装包；安装包只包含完整结构与 release 必要数据，不携带运行期全量数据。
 */
function runReleaseSnapshot(bool $strictIsolation): void
{
    $workingDirectory = getcwd() ?: '.';
    $projectRoot = realpath($workingDirectory) ?: $workingDirectory;
    $controlledParent = str_replace('\\', '/', rtrim($projectRoot, '/\\') . '/storage/extra');
    $finalPath = $controlledParent . '/release';
    $token = date('YmdHis') . '_' . getmypid() . '_' . bin2hex(random_bytes(4));
    $stagingPath = $controlledParent . '/release.staging-' . $token;
    // 新一轮生成开始即让旧 final 失效；任何失败都保持 final 缺失，禁止误打包上一轮安装快照。
    invalidateReleaseInstallArtifacts();
    removePath($stagingPath);
    if (!is_dir($controlledParent) && !mkdir($controlledParent, 0755, true) && !is_dir($controlledParent)) {
        throw new RuntimeException("创建数据库安装包受控目录失败：{$controlledParent}");
    }
    $controlledRealPath = realpath($controlledParent);
    if ($controlledRealPath === false
        || str_replace('\\', '/', $controlledRealPath) !== $controlledParent
        || is_link($controlledParent)) {
        throw new RuntimeException("数据库安装包受控目录必须是项目内真实目录：{$controlledParent}");
    }

    $temporaryDatabases = [];
    $failure = null;
    $cleanupFailure = null;

    try {
        if ($strictIsolation) {
            buildIsolatedReleaseSnapshot($stagingPath, $temporaryDatabases);
        } else {
            buildConfiguredReleaseSnapshot($stagingPath, $temporaryDatabases);
        }
    } catch (Throwable $throwable) {
        $failure = $throwable;
    } finally {
        foreach (array_reverse($temporaryDatabases) as $temporaryDatabase) {
            try {
                cleanupTemporaryReleaseDatabase($temporaryDatabase);
            } catch (Throwable $throwable) {
                $cleanupFailure ??= $throwable;
            }
        }
    }

    if ($failure !== null || $cleanupFailure !== null) {
        removePath($stagingPath);
        removePath($finalPath);
        $messages = [];
        if ($failure !== null) {
            $messages[] = $failure->getMessage();
        }
        if ($cleanupFailure !== null) {
            $messages[] = '清理隔离数据库失败：' . $cleanupFailure->getMessage();
        }
        throw new RuntimeException(implode(PHP_EOL, $messages), 0, $failure ?? $cleanupFailure);
    }

    try {
        assertReleaseInstallStaging($stagingPath);
        if (!rename($stagingPath, $finalPath)) {
            throw new RuntimeException("原子发布数据库安装包失败：{$finalPath}");
        }
    } catch (Throwable $throwable) {
        removePath($stagingPath);
        removePath($finalPath);
        throw $throwable;
    }
}

/**
 * 日常构建从 `.env` 当前库采集必要数据和当前方言结构，只对另一方言创建临时源与恢复目标。
 *
 * 配置库只执行 backup 与 restore dry-run，不执行 migrate、restore 或任何建库删库操作；当前配置为
 * MySQL 时，整个日常构建不会申请 CREATE/DROP DATABASE 权限。
 *
 * @param array<int,array{driver:string,database:string,directory:?string,environment:array<string,string>}> $temporaryDatabases
 */
function buildConfiguredReleaseSnapshot(string $stagingPath, array &$temporaryDatabases): void
{
    $configuredEnvironment = releaseBuildEnvironment();
    $configuredDriver = normalizeReleaseDatabaseDriver($configuredEnvironment['DB_DRIVER']);
    $token = date('YmdHis') . '_' . getmypid() . '_' . bin2hex(random_bytes(4));
    $configuredEnvironment['APP_ENV'] = 'dev';
    $configuredEnvironment['CACHE_DRIVER'] = 'file';
    $configuredEnvironment['CACHE_PREFIX'] = 'release_build_configured_' . $token;

    $captureEnvironment = array_merge($configuredEnvironment, [
        'RELEASE_INSTALL_STAGING_DIR' => $stagingPath,
        'RELEASE_INSTALL_WRITE_DATA' => '1',
    ]);
    runBuildCommand(
        "从 .env {$configuredDriver} 数据库采集安装快照",
        ['./bin/smart.php', 'xadmin:release:backup', '--install'],
        false,
        $captureEnvironment
    );

    $secondaryDriver = $configuredDriver === 'mysql' ? 'sqlite' : 'mysql';
    $secondarySource = createTemporaryReleaseDatabase($secondaryDriver, 'source');
    $temporaryDatabases[] = $secondarySource;
    prepareTemporaryReleaseSource($secondarySource);
    $secondaryEnvironment = array_merge($secondarySource['environment'], [
        'RELEASE_INSTALL_STAGING_DIR' => $stagingPath,
        'RELEASE_INSTALL_WRITE_DATA' => '0',
    ]);
    runBuildCommand(
        "从 {$secondaryDriver} fresh 隔离库补充兼容结构",
        ['./bin/smart.php', 'xadmin:release:backup', '--install'],
        false,
        $secondaryEnvironment
    );

    assertReleaseInstallStaging($stagingPath);

    $configuredVerifyReport = runBuildJsonCommand(
        "只读复查 .env {$configuredDriver} 数据库与安装快照",
        ['./bin/smart.php', 'xadmin:release:restore', '--install', '--dry-run', '--json'],
        array_merge($configuredEnvironment, ['RELEASE_INSTALL_STAGING_DIR' => $stagingPath])
    );
    assertConfiguredReleaseSource($configuredDriver, $stagingPath, $configuredVerifyReport);

    $secondaryTarget = createTemporaryReleaseDatabase($secondaryDriver, 'target');
    $temporaryDatabases[] = $secondaryTarget;
    $targetEnvironment = array_merge($secondaryTarget['environment'], [
        'RELEASE_INSTALL_STAGING_DIR' => $stagingPath,
    ]);
    $restoreReport = runBuildJsonCommand(
        "在空 {$secondaryDriver} 目标真实恢复安装快照",
        ['./bin/smart.php', 'xadmin:release:restore', '--install', '--json'],
        $targetEnvironment
    );
    $verifyReport = runBuildJsonCommand(
        "复查 {$secondaryDriver} 安装目标结构差异",
        ['./bin/smart.php', 'xadmin:release:restore', '--install', '--dry-run', '--json'],
        $targetEnvironment
    );
    assertRestoredReleaseTarget($secondaryDriver, $stagingPath, $restoreReport, $verifyReport);
}

/**
 * CI 与完整发布检查继续从 fresh SQLite/MySQL 双源生成，并在两个空目标真实恢复。
 *
 * @param array<int,array{driver:string,database:string,directory:?string,environment:array<string,string>}> $temporaryDatabases
 */
function buildIsolatedReleaseSnapshot(string $stagingPath, array &$temporaryDatabases): void
{
    foreach (['sqlite', 'mysql'] as $driver) {
        $source = createTemporaryReleaseDatabase($driver, 'source');
        $temporaryDatabases[] = $source;
        prepareTemporaryReleaseSource($source);
        $environment = array_merge($source['environment'], [
            'RELEASE_INSTALL_STAGING_DIR' => $stagingPath,
            'RELEASE_INSTALL_WRITE_DATA' => $driver === 'sqlite' ? '1' : '0',
        ]);
        runBuildCommand(
            "从 {$driver} fresh 隔离库采集安装快照",
            ['./bin/smart.php', 'xadmin:release:backup', '--install'],
            false,
            $environment
        );
    }

    assertReleaseInstallStaging($stagingPath);

    foreach (['sqlite', 'mysql'] as $driver) {
        $target = createTemporaryReleaseDatabase($driver, 'target');
        $temporaryDatabases[] = $target;
        $environment = array_merge($target['environment'], [
            'RELEASE_INSTALL_STAGING_DIR' => $stagingPath,
        ]);
        $restoreReport = runBuildJsonCommand(
            "在空 {$driver} 目标真实恢复安装快照",
            ['./bin/smart.php', 'xadmin:release:restore', '--install', '--json'],
            $environment
        );
        $verifyReport = runBuildJsonCommand(
            "复查 {$driver} 安装目标结构差异",
            ['./bin/smart.php', 'xadmin:release:restore', '--install', '--dry-run', '--json'],
            $environment
        );
        assertRestoredReleaseTarget($driver, $stagingPath, $restoreReport, $verifyReport);
    }
}

/**
 * `.env` 源只能做 dry-run 比较；出现结构差异时说明当前开发库尚未同步，禁止把不一致快照打包。
 *
 * @param array<string,mixed> $verifyReport
 */
function assertConfiguredReleaseSource(string $driver, string $stagingPath, array $verifyReport): void
{
    $schemaPath = str_replace('\\', '/', (string)($verifyReport['schema_path'] ?? ''));
    if (
        ($verifyReport['install'] ?? null) !== true
        || ($verifyReport['dry_run'] ?? null) !== true
        || !str_ends_with($schemaPath, "/database.schema.{$driver}.gz")
        || (array)($verifyReport['safe_sql'] ?? []) !== []
        || (array)($verifyReport['destructive_sql'] ?? []) !== []
        || !is_file($stagingPath . "/database.schema.{$driver}.gz")
    ) {
        throw new RuntimeException(".env {$driver} 数据库与刚采集的安装快照存在结构差异");
    }
}

function normalizeReleaseDatabaseDriver(string $driver): string
{
    $driver = strtolower(trim($driver));
    $driver = str_starts_with($driver, 'pdo_') ? substr($driver, 4) : $driver;
    if (!in_array($driver, ['sqlite', 'mysql'], true)) {
        throw new RuntimeException("发布安装快照只支持 SQLite 或 MySQL，当前 .env 驱动：{$driver}");
    }

    return $driver;
}

/**
 * 空目标恢复必须写入完整必要数据，且恢复后的第二次比较不能再产生任何结构 SQL。
 *
 * @param array<string,mixed> $restoreReport
 * @param array<string,mixed> $verifyReport
 */
function assertRestoredReleaseTarget(string $driver, string $stagingPath, array $restoreReport, array $verifyReport): void
{
    $meta = json_decode((string)file_get_contents($stagingPath . '/database.meta.json'), true, 512, JSON_THROW_ON_ERROR);
    $schemaFilename = "database.schema.{$driver}.gz";
    $schemaPath = str_replace('\\', '/', (string)($restoreReport['schema_path'] ?? ''));
    if (
        ($restoreReport['install'] ?? null) !== true
        || ($restoreReport['dry_run'] ?? null) !== false
        || !str_ends_with($schemaPath, '/' . $schemaFilename)
        || (array)($restoreReport['skipped_tables'] ?? []) !== []
        || (int)($restoreReport['data_rows'] ?? -1) !== (int)($meta['data_rows'] ?? -2)
    ) {
        throw new RuntimeException("{$driver} 安装快照恢复结果与 staging 元数据不一致");
    }
    if (
        ($verifyReport['install'] ?? null) !== true
        || ($verifyReport['dry_run'] ?? null) !== true
        || (array)($verifyReport['safe_sql'] ?? []) !== []
        || (array)($verifyReport['destructive_sql'] ?? []) !== []
    ) {
        throw new RuntimeException("{$driver} 安装快照恢复验证仍存在结构差异");
    }
}

/**
 * 在 fresh 源库执行迁移与注册表同步；目标验证库保持创建后的真正空库，不复用源库状态。
 *
 * @param array{driver:string,database:string,directory:?string,environment:array<string,string>} $temporaryDatabase
 */
function prepareTemporaryReleaseSource(array $temporaryDatabase): void
{
    $driver = $temporaryDatabase['driver'];
    $environment = $temporaryDatabase['environment'];
    runBuildCommand("在 {$driver} 隔离源重建完整结构", [
        './bin/smart.php',
        'migrate:fresh',
        '--force',
        '--no-interaction',
    ], false, $environment);
    runBuildCommand("在 {$driver} 隔离源同步菜单种子", [
        './bin/smart.php',
        'xadmin:menu:sync',
        '--details',
    ], false, $environment);
    runBuildCommand("在 {$driver} 隔离源同步权限节点", [
        './bin/smart.php',
        'xadmin:node:sync',
        '--details',
    ], false, $environment);
}

/**
 * staging 只有在双 schema、共享 data、元数据映射和哈希全部一致时才允许发布。
 */
function assertReleaseInstallStaging(string $stagingPath): void
{
    $normalized = rtrim(str_replace('\\', '/', $stagingPath), '/');
    $controlledParent = rtrim(str_replace('\\', '/', (getcwd() ?: '.') . '/storage/extra'), '/');
    $controlledRealPath = realpath($controlledParent);
    $stagingRealPath = realpath($normalized);
    if (
        dirname($normalized) !== $controlledParent
        || preg_match('/^release\.staging-[a-zA-Z0-9][a-zA-Z0-9._-]*$/', basename($normalized)) !== 1
        || is_link($normalized)
        || $controlledRealPath === false
        || $stagingRealPath === false
        || str_replace('\\', '/', $controlledRealPath) !== $controlledParent
        || str_replace('\\', '/', dirname($stagingRealPath)) !== str_replace('\\', '/', $controlledRealPath)
    ) {
        throw new RuntimeException("数据库安装包 staging 必须是受控构建目录中的真实直属目录：{$stagingPath}");
    }

    $required = [
        'database.schema.mysql.gz',
        'database.schema.sqlite.gz',
        'database.data.gz',
        'database.meta.json',
    ];
    foreach ($required as $filename) {
        $path = $stagingPath . '/' . $filename;
        if (!is_file($path) || filesize($path) <= 0) {
            throw new RuntimeException("数据库安装包 staging 缺失或为空：{$path}");
        }
    }
    if (is_file($stagingPath . '/database.schema.gz')) {
        throw new RuntimeException('数据库安装包 format v2 禁止包含旧 database.schema.gz');
    }

    $meta = json_decode((string)file_get_contents($stagingPath . '/database.meta.json'), true);
    if (
        !is_array($meta)
        || (int)($meta['format_version'] ?? 0) !== 2
        || ($meta['kind'] ?? null) !== 'install'
        || ($meta['with_data'] ?? true) !== false
    ) {
        throw new RuntimeException('数据库安装包 staging 元数据必须为 format_version=2、kind=install、with_data=false');
    }
    $databasePrefix = $meta['database_prefix'] ?? null;
    $expectedDatabasePrefix = releaseBuildEnvironment()['DB_PREFIX'];
    if (
        !is_string($databasePrefix)
        || $databasePrefix !== trim($databasePrefix)
        || ($databasePrefix !== '' && preg_match('/^[a-zA-Z0-9_]+$/D', $databasePrefix) !== 1)
        || $databasePrefix !== $expectedDatabasePrefix
    ) {
        throw new RuntimeException('数据库安装包 staging 的 database_prefix 与构建环境 DB_PREFIX 不一致');
    }

    $schemas = is_array($meta['schema'] ?? null) ? $meta['schema'] : [];
    $projectRequiredStructures = (glob('plugin/*/stc/migrations/*_project_task_acceptance_criteria.php') ?: []) !== []
        ? ['acceptance_criteria', 'task_category', 'project_task_acceptance_snapshot']
        : [];
    foreach (['mysql', 'sqlite'] as $driver) {
        $filename = "database.schema.{$driver}.gz";
        $entry = is_array($schemas[$driver] ?? null) ? $schemas[$driver] : [];
        $path = $stagingPath . '/' . $filename;
        if (
            (string)($entry['driver'] ?? '') !== $driver
            || (string)($entry['file'] ?? '') !== $filename
            || !hash_equals((string)($entry['sha256'] ?? ''), (string)hash_file('sha256', $path))
        ) {
            throw new RuntimeException("数据库安装包 {$driver} schema 映射或哈希不一致");
        }
        $payload = gzdecode((string)file_get_contents($path));
        if (!is_string($payload) || $payload === '') {
            throw new RuntimeException("数据库安装包 {$driver} schema 无法解压");
        }
        foreach (['kpi_item', 'idx_proj_task_perf_kpi'] as $legacyName) {
            if (str_contains($payload, $legacyName)) {
                throw new RuntimeException("数据库安装包 {$driver} schema 仍包含已删除结构：{$legacyName}");
            }
        }
        foreach ($projectRequiredStructures as $requiredName) {
            if (!str_contains($payload, $requiredName)) {
                throw new RuntimeException("数据库安装包 {$driver} schema 缺少当前任务验收结构：{$requiredName}");
            }
        }
    }

    $data = is_array($meta['data'] ?? null) ? $meta['data'] : [];
    $dataPath = $stagingPath . '/database.data.gz';
    if (
        (string)($data['file'] ?? '') !== 'database.data.gz'
        || !hash_equals((string)($data['sha256'] ?? ''), (string)hash_file('sha256', $dataPath))
    ) {
        throw new RuntimeException('数据库安装包共享 data 映射或哈希不一致');
    }
    if ((array)($meta['skipped_tables'] ?? []) !== []) {
        throw new RuntimeException('数据库安装包 fresh 源缺少必要数据表，禁止发布不完整安装包');
    }
}

/**
 * 创建发布快照专用数据库。所有后续命令只通过子进程环境变量使用该库，当前进程和原 `.env` 始终不变。
 *
 * @return array{driver:string,database:string,directory:?string,environment:array<string,string>}
 */
function createTemporaryReleaseDatabase(string $driver, string $role): array
{
    $source = releaseBuildEnvironment();
    $driver = strtolower(trim($driver));
    if (!in_array($driver, ['sqlite', 'mysql'], true)) {
        throw new InvalidArgumentException("发布快照仅支持 SQLite 或 MySQL，当前驱动：{$driver}");
    }
    $role = strtolower(trim($role));
    if (!in_array($role, ['source', 'target'], true)) {
        throw new InvalidArgumentException("不支持的发布隔离库角色：{$role}");
    }
    $token = $role . '_' . date('YmdHis') . '_' . getmypid() . '_' . bin2hex(random_bytes(6));
    $environment = $source;
    $environment['APP_ENV'] = 'dev';
    $environment['CACHE_DRIVER'] = 'file';
    $environment['CACHE_PREFIX'] = 'release_build_' . $token;

    if ($driver === 'sqlite') {
        $directory = str_replace('\\', '/', (getcwd() ?: '.') . '/runtime/release-build/' . $token);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException("创建 SQLite 隔离目录失败：{$directory}");
        }
        $database = $directory . '/system.db';
        if (file_put_contents($database, '', LOCK_EX) === false) {
            removePath($directory);
            throw new RuntimeException("创建 SQLite 隔离数据库失败：{$database}");
        }
        $environment['DB_DRIVER'] = 'sqlite';
        $environment['DB_DATABASE'] = $database;

        echo "[build] 使用 SQLite 隔离数据库 {$database}" . PHP_EOL;
        return [
            'driver' => 'sqlite',
            'database' => $database,
            'directory' => $directory,
            'environment' => $environment,
        ];
    }

    $database = '_release_build_' . $token;
    assertTemporaryMysqlDatabaseName($database);
    $environment['DB_DRIVER'] = 'mysql';
    $environment['DB_DATABASE'] = $database;
    controlTemporaryMysqlDatabase('create', $database, $environment);
    rememberTemporaryMysqlDatabase($database);

    echo "[build] 使用 MySQL 隔离数据库 {$database}" . PHP_EOL;
    return [
        'driver' => 'mysql',
        'database' => $database,
        'directory' => null,
        'environment' => $environment,
    ];
}

/**
 * @param array{driver:string,database:string,directory:?string,environment:array<string,string>} $temporaryDatabase
 */
function cleanupTemporaryReleaseDatabase(array $temporaryDatabase): void
{
    if ($temporaryDatabase['driver'] === 'sqlite') {
        $directory = (string)$temporaryDatabase['directory'];
        $expectedRoot = str_replace('\\', '/', (getcwd() ?: '.') . '/runtime/release-build/');
        $normalized = rtrim(str_replace('\\', '/', $directory), '/') . '/';
        if ($directory === '' || !str_starts_with($normalized, $expectedRoot)) {
            throw new RuntimeException("拒绝清理非发布隔离目录：{$directory}");
        }
        removePath($directory);
        @rmdir(dirname($directory));
        return;
    }

    assertTemporaryMysqlDatabaseName($temporaryDatabase['database']);
    if (!ownsTemporaryMysqlDatabase($temporaryDatabase['database'])) {
        throw new RuntimeException("拒绝删除非本次进程创建的 MySQL 发布隔离数据库：{$temporaryDatabase['database']}");
    }
    controlTemporaryMysqlDatabase('drop', $temporaryDatabase['database'], $temporaryDatabase['environment']);
    forgetTemporaryMysqlDatabase($temporaryDatabase['database']);
}

/**
 * MySQL 临时库名固定使用受控前缀；DROP 前再次校验，避免环境或参数错误伤及现有数据库。
 */
function assertTemporaryMysqlDatabaseName(string $database): void
{
    if (!preg_match('/^_release_build_[a-z0-9_]+$/', $database)) {
        throw new RuntimeException("拒绝操作非发布隔离 MySQL 数据库：{$database}");
    }
}

function rememberTemporaryMysqlDatabase(string $database): void
{
    $GLOBALS['xadmin_release_build_mysql_databases'][$database] = true;
}

function ownsTemporaryMysqlDatabase(string $database): bool
{
    return isset($GLOBALS['xadmin_release_build_mysql_databases'][$database]);
}

function forgetTemporaryMysqlDatabase(string $database): void
{
    unset($GLOBALS['xadmin_release_build_mysql_databases'][$database]);
}

/**
 * @param array<string,string> $environment
 */
function controlTemporaryMysqlDatabase(string $action, string $database, array $environment): void
{
    if (!in_array($action, ['create', 'drop'], true)) {
        throw new InvalidArgumentException("不支持的 MySQL 隔离库动作：{$action}");
    }
    assertTemporaryMysqlDatabaseName($database);

    $code = <<<'PHP_CODE'
$action = (string)getenv('RELEASE_BUILD_DB_ACTION');
$database = (string)getenv('RELEASE_BUILD_DB_NAME');
if (!in_array($action, ['create', 'drop'], true) || !preg_match('/^_release_build_[a-z0-9_]+$/', $database)) {
    fwrite(STDERR, "拒绝操作非发布隔离 MySQL 数据库\n");
    exit(64);
}
$charset = (string)(getenv('DB_CHARSET') ?: 'utf8mb4');
$collation = (string)(getenv('DB_COLLATION') ?: 'utf8mb4_unicode_ci');
if (!preg_match('/^[a-z0-9_]+$/i', $charset) || !preg_match('/^[a-z0-9_]+$/i', $collation)) {
    fwrite(STDERR, "MySQL 字符集或排序规则不安全\n");
    exit(64);
}
try {
    $pdo = new PDO(
        sprintf(
            'mysql:host=%s;port=%d;charset=%s',
            (string)(getenv('DB_HOST') ?: '127.0.0.1'),
            (int)(getenv('DB_PORT') ?: 3306),
            $charset
        ),
        (string)getenv('DB_USERNAME'),
        (string)getenv('DB_PASSWORD'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $quoted = '`' . $database . '`';
    if ($action === 'create') {
        $pdo->exec("CREATE DATABASE {$quoted} CHARACTER SET {$charset} COLLATE {$collation}");
    } else {
        $pdo->exec("DROP DATABASE {$quoted}");
    }
} catch (Throwable $throwable) {
    fwrite(STDERR, sprintf(
        "MySQL 发布隔离库%s失败；构建账号必须具备 CREATE/DROP DATABASE 权限：%s\n",
        $action === 'create' ? '创建' : '删除',
        $throwable->getMessage()
    ));
    exit(1);
}
PHP_CODE;

    $environment['RELEASE_BUILD_DB_ACTION'] = $action;
    $environment['RELEASE_BUILD_DB_NAME'] = $database;
    runBuildCommand(
        ($action === 'create' ? '创建' : '删除') . ' MySQL 发布隔离数据库',
        ['./bin/smart.php', 'runtime', '-r', $code],
        false,
        $environment
    );
}

/**
 * 读取构建数据库环境，显式进程变量优先于 `.env`；敏感值只传给子进程，不输出到构建日志。
 *
 * @return array<string,string>
 */
function releaseBuildEnvironment(): array
{
    $fileValues = [];
    foreach (['.env', '.env.example'] as $filename) {
        if (is_file($filename)) {
            $fileValues = readReleaseEnvFile($filename);
            break;
        }
    }

    $defaults = [
        'DB_DRIVER' => 'sqlite',
        'DB_HOST' => '127.0.0.1',
        'DB_PORT' => '3306',
        'DB_DATABASE' => 'runtime/system.db',
        'DB_USERNAME' => 'root',
        'DB_PASSWORD' => '',
        'DB_PREFIX' => '',
        'DB_CHARSET' => 'utf8mb4',
        'DB_COLLATION' => 'utf8mb4_unicode_ci',
        'CACHE_DRIVER' => 'file',
        'CACHE_PREFIX' => 'smartadmin',
    ];
    $result = [];
    foreach ($defaults as $name => $default) {
        $processValue = getenv($name);
        $result[$name] = $processValue !== false ? (string)$processValue : (string)($fileValues[$name] ?? $default);
    }
    $result['DB_PREFIX'] = trim($result['DB_PREFIX']);
    if ($result['DB_PREFIX'] !== '' && preg_match('/^[a-zA-Z0-9_]+$/D', $result['DB_PREFIX']) !== 1) {
        throw new RuntimeException('DB_PREFIX 只能包含字母、数字和下划线');
    }

    return $result;
}

/**
 * @return array<string,string>
 */
function readReleaseEnvFile(string $path): array
{
    $values = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_starts_with($line, 'export ')) {
            $line = substr($line, 7);
        }
        $separator = strpos($line, '=');
        if ($separator === false) {
            continue;
        }
        $name = trim(substr($line, 0, $separator));
        if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $name)) {
            continue;
        }
        $value = trim(substr($line, $separator + 1));
        if (strlen($value) >= 2) {
            $quote = $value[0];
            if (($quote === '"' || $quote === "'") && str_ends_with($value, $quote)) {
                $value = substr($value, 1, -1);
            }
        }
        $values[$name] = $value;
    }

    return $values;
}

/**
 * 清理发布工作区；只删除明确构建产物和容器预编译缓存，不删除源码 public/runtime、vendor、composer.lock 或升级模板脚本。
 */
function cleanReleaseWorkspace(): void
{
    invalidateReleaseInstallArtifacts();
    removePaths(array_merge(
        glob('build/system*') ?: [],
        glob('build/upgrade/system*') ?: [],
        [
            'system.bin',
            'build/.env',
            'build/.env.example',
            'build/.DS_Store',
            'build/public',
            'build/runtime',
            'runtime/container',
        ]
    ));
}

/**
 * 作废当前可发布安装包及上次异常留下的 staging。
 *
 * 只处理固定的 storage/extra/release 边界，不扫描或删除其它 storage 数据。
 */
function invalidateReleaseInstallArtifacts(): void
{
    removePath('storage/extra/release');
    removePaths(glob('storage/extra/release.staging-*') ?: []);
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

    runBuildCommand('生成 Hyperf 扫描缓存', ['./bin/smart.php', 'list', '--no-ansi', '--no-interaction'], true, [
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
 * 打包脚本本身必须运行在 PHP 8.4+，避免生成低版本无法加载的 classmap 或缓存。
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
    foreach (['./bin/smart.php', 'runtime', '-r', $code] as $argument) {
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
        'storage/extra/release/database.schema.mysql.gz',
        'storage/extra/release/database.schema.sqlite.gz',
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
        'storage/extra/release/database.schema.mysql.gz',
        'storage/extra/release/database.schema.sqlite.gz',
        'storage/extra/release/database.data.gz',
        'storage/extra/release/database.meta.json',
    ];
    $errors = [];
    foreach ($required as $path) {
        if (!isset($phar[$path]) || $phar[$path]->getSize() <= 0) {
            $errors[] = "Phar 缺少数据库安装包：{$path}";
        }
    }

    if (isset($phar['storage/extra/release/database.schema.gz'])) {
        $errors[] = '数据库安装包 format v2 不能包含旧 database.schema.gz';
    }
    if (isset($phar['storage/extra/release/database.meta.json'])) {
        $meta = json_decode($phar['storage/extra/release/database.meta.json']->getContent(), true);
        if (
            !is_array($meta)
            || (int)($meta['format_version'] ?? 0) !== 2
            || ($meta['kind'] ?? null) !== 'install'
            || ($meta['with_data'] ?? true) !== false
        ) {
            $errors[] = '数据库安装包元数据非法，必须 format_version=2、kind=install 且 with_data=false';
        } else {
            $databasePrefix = $meta['database_prefix'] ?? null;
            if (
                !is_string($databasePrefix)
                || $databasePrefix !== trim($databasePrefix)
                || ($databasePrefix !== '' && preg_match('/^[a-zA-Z0-9_]+$/D', $databasePrefix) !== 1)
            ) {
                $errors[] = '数据库安装包 database_prefix 缺失或非法';
            }
            $schemas = is_array($meta['schema'] ?? null) ? $meta['schema'] : [];
            foreach (['mysql', 'sqlite'] as $driver) {
                $filename = "database.schema.{$driver}.gz";
                $path = 'storage/extra/release/' . $filename;
                $entry = is_array($schemas[$driver] ?? null) ? $schemas[$driver] : [];
                $content = isset($phar[$path]) ? $phar[$path]->getContent() : '';
                if (
                    (string)($entry['driver'] ?? '') !== $driver
                    || (string)($entry['file'] ?? '') !== $filename
                    || !hash_equals((string)($entry['sha256'] ?? ''), hash('sha256', $content))
                ) {
                    $errors[] = "数据库安装包 {$driver} schema 映射或哈希非法";
                }
            }
            $data = is_array($meta['data'] ?? null) ? $meta['data'] : [];
            $dataPath = 'storage/extra/release/database.data.gz';
            $dataContent = isset($phar[$dataPath]) ? $phar[$dataPath]->getContent() : '';
            if (
                (string)($data['file'] ?? '') !== 'database.data.gz'
                || !hash_equals((string)($data['sha256'] ?? ''), hash('sha256', $dataContent))
            ) {
                $errors[] = '数据库安装包共享 data 映射或哈希非法';
            }
        }
    }

    return $errors;
}

/**
 * 审计 Phar 内 web-dist.zip：必须包含 index.html + static/，并排除动态配置、本机元数据和不安全路径。
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
        $hasStatic = false;
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
                } elseif (!str_starts_with($relative, 'static/')) {
                    $errors[] = "前端资源包包含非 static 根路径：{$relative}";
                }
                if (str_starts_with($relative, 'static/')) {
                    $hasStatic = true;
                }
            }
        } finally {
            $zip->close();
        }

        if (!$hasIndex) {
            $errors[] = '前端资源包缺少入口页：index.html';
        }
        if (!$hasStatic) {
            $errors[] = '前端资源包缺少静态目录：static';
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
 * 读取 Composer installed.json，并支持 Composer 1/2 结构。
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
    exec(escapeshellarg('./bin/smart.php') . ' ' . escapeshellarg('runtime') . ' -r ' . escapeshellarg($code), $output, $status);
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
