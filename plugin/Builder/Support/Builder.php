<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://doc.hyperf.thinkadmin.top
 */

namespace Builder\Support;

use Builder\Ast\Ast;
use Builder\Ast\Visitor\RewriteConfigFactoryVisitor;
use Builder\Ast\Visitor\RewriteConfigVisitor;
use Builder\Ast\Visitor\UnshiftCodeStringVisitor;
use Hyperf\Contract\StdoutLoggerInterface;
use Symfony\Component\Finder\Finder;

/**
 * 构建 Phar 包的核心类.
 */
class Builder
{
    private const PLUGIN_MANIFEST_FILE = 'plugin.json';

    /**
     * Phar 内前端资源压缩包路径；运行期由 FrontendPublisher 解压到 public，避免直接在 Phar 中暴露 web/dist 目录树。
     */
    private const FRONTEND_ARCHIVE_PATH = 'storage/extra/web-dist.zip';

    /**
     * 前端资源包最小入口文件；缺失时发布包无法提供 SPA 入口，构建阶段直接失败。
     */
    private const FRONTEND_REQUIRED_ENTRIES = ['index.html'];

    /**
     * 运行环境动态生成的前端配置，不能进入资源压缩包，避免部署后覆盖 .env 注入值。
     */
    private const FRONTEND_DYNAMIC_CONFIGS = ['_app.config.js'];

    /**
     * plugin.json 支持的标准资源声明字段；只读取 plugin 对象下的显式配置。
     */
    private const PLUGIN_RESOURCE_FIELDS = [
        'view_root',
        'language_root',
        'migration_root',
    ];

    /**
     * 仅对项目一方代码与自有包做源码加固；第三方 vendor 保持原样，避免破坏未知包的反射和元数据。
     */
    private const PHP_SOURCE_STRIP_PATH_PREFIXES = [
        'app/',
        'bin/',
        'config/',
        'migrations/',
        'runtime/container/',
        'plugin/',
        'vendor/zoujingli/',
    ];

    private Package $package;

    private string|Target|null $target = null;

    private array $mount = [];

    private ?string $version = null;

    private ?string $main = null;

    /**
     * Builder 构造函数.
     *
     * @param string $path composer.json 路径
     * @param StdoutLoggerInterface $logger 日志输出对象
     */
    public function __construct(string $path, private readonly StdoutLoggerInterface $logger)
    {
        $this->package = new Package($this->loadJson($path), dirname(realpath($path)));
    }

    /**
     * 获取 Phar 包的最终文件名.
     */
    public function getTarget(): string
    {
        if ($this->target === null) {
            $target = $this->package->getShortName();
            if ($this->version !== null) {
                $target .= ':' . $this->version;
            }
            $this->target = $target . '.phar';
        }
        return (string)$this->target;
    }

    /**
     * 设置 Phar 包名称.
     *
     * @return $this
     */
    public function setTarget(string|Target $target): static
    {
        if (is_dir($target)) {
            $this->target = null;
            $target = rtrim($target, '/') . '/' . $this->getTarget();
        }
        $this->target = $target;
        return $this;
    }

    /**
     * 设置版本号.
     *
     * @return $this
     */
    public function setVersion(string $version): static
    {
        $this->version = $version;
        return $this;
    }

    /**
     * 获取默认启动脚本路径.
     */
    public function getMain(): string
    {
        if ($this->main === null) {
            foreach ($this->package->getBins() as $path) {
                if (!file_exists($this->package->getDirectory() . $path)) {
                    throw new \UnexpectedValueException('Bin file "' . $path . '" does not exist');
                }
                $this->main = $path;
                break;
            }
            // 默认使用 hyperf bootstrap 文件
            if ($this->main == null) {
                return 'bin/hyperf.php';
            }
        }
        return $this->main;
    }

    /**
     * 设置默认启动文件.
     *
     * @return $this
     */
    public function setMain(string $main): static
    {
        $this->main = $main;
        return $this;
    }

    /**
     * 获取 Package 对象
     */
    public function getPackage(): Package
    {
        return $this->package;
    }

    /**
     * 设置挂载目录.
     *
     * @param array $mount ['外部路径:内部路径']
     * @return $this
     */
    public function setMount(array $mount = []): static
    {
        foreach ($mount as $item) {
            $items = explode(':', $item);
            $this->mount[$items[0]] = $items[1] ?? $items[0];
        }

        return $this;
    }

    /**
     * 获取挂载目录.
     */
    public function getMount(): array
    {
        return $this->mount;
    }

    /**
     * 获取所有依赖包列表.
     *
     * @return Package[]
     */
    public function getPackagesDependencies(): array
    {
        $packages = [];
        $vendorPath = $this->package->getVendorAbsolutePath();
        // 获取所有已安装的依赖包
        if (is_file($vendorPath . 'composer/installed.json')) {
            $installed = $this->loadJson($vendorPath . 'composer/installed.json');
            $installedPackages = $installed;
            // Composer 2.0 适配
            if (isset($installed['packages'])) {
                $installedPackages = $installed['packages'];
            }
            foreach ($installedPackages as $package) {
                // 支持自定义安装路径
                $dir = 'composer/' . ($package['install-path'] ?? '../' . $package['name']) . '/';
                if (isset($package['target-dir'])) {
                    $dir .= trim($package['target-dir'], '/') . '/';
                }
                $packages[] = new Package($package, $this->canonicalize($vendorPath . $dir));
            }
        }
        return $packages;
    }

    /**
     * 将路径标准化.
     */
    public function canonicalize(mixed $address): array|string
    {
        $address = explode('/', $address);
        $keys = array_keys($address, '..');
        foreach ($keys as $pos => $key) {
            array_splice($address, $key - ($pos * 2 + 1), 2);
        }
        return str_replace('./', '', implode('/', $address));
    }

    /**
     * 获取相对于资源包的相对路径.
     */
    public function getPathLocalToBase(string $path): ?string
    {
        $path = str_replace('\\', '/', $path);
        $root = str_replace('\\', '/', $this->package->getDirectory());
        if (!str_starts_with($path, $root)) {
            throw new \UnexpectedValueException('Path "' . $path . '" is not within base project path "' . $root . '"');
        }
        $basePath = substr($path, strlen($root));
        return empty($basePath) ? null : $this->canonicalize($basePath);
    }

    /**
     * 生成挂载链接的 PHP 代码
     */
    public function getMountLinkCode(): string
    {
        $mountString = '';
        foreach ($this->getMount() as $link => $inside) {
            $mountString .= "'{$link}' => '{$inside}',";
        }

        return <<<EOD
<?php
\$mount = [{$mountString}];
\$path = dirname(realpath(\$argv[0]));
@ini_set('phar.readonly', 'On');
\$pharRuntime = \\Phar::running(false);
\$pharRoot = str_starts_with(\$pharRuntime, 'phar://') ? \$pharRuntime : 'phar://' . \$pharRuntime;
require_once \$pharRuntime === '' ? dirname(__DIR__) . '/xadmin_obfuscate.php' : \$pharRoot . '/xadmin_obfuscate.php';
array_walk(\$mount, function (\$item, \$link) use (\$path) {
    \$file = \$link;
    if(ltrim(\$link, '/') == \$link){
        \$file = \$path . '/' . \$link;
    }
    if(!file_exists(\$file)){
        if(rtrim(\$item, '/')!=\$item){
            @mkdir(\$file, 0777, true);
        }else{
            file_exists(dirname(\$file)) || @mkdir(dirname(\$file), 0777, true);
            file_put_contents(\$file,"");
        }
    }
    Phar::mount(\$item,\$file);
});
EOD;
    }

    /**
     * 方法体字符串混淆的运行时解码函数，入口启动时提前加载，保证类自动加载前可用。
     */
    public function getObfuscationRuntimeCode(): string
    {
        return <<<'PHP'
<?php
declare(strict_types=1);

if (!function_exists('xadmin_obf_s')) {
    function xadmin_obf_s(string $value, int $key): string
    {
        // 混淆字符串均为构建期常量，同一 worker 生命周期内可安全缓存，避免热点路径反复 base64 与异或解码。
        static $cache = [], $tables = [];
        if (isset($cache[$key][$value])) {
            return $cache[$key][$value];
        }

        $binary = base64_decode(strrev($value), true);
        if ($binary === false) {
            return $cache[$key][$value] = '';
        }

        if (!isset($tables[$key])) {
            $from = $to = '';
            for ($index = 0; $index < 256; $index++) {
                $from .= chr($index);
                $to .= chr($index ^ $key);
            }
            $tables[$key] = [$from, $to];
        }

        return $cache[$key][$value] = strtr($binary, $tables[$key][0], $tables[$key][1]);
    }
}
PHP;
    }

    /**
     * 构建 Phar 文件.
     */
    public function build(): void
    {
        $time = microtime(true);
        $this->logger->info('Creating phar <info>' . $this->getTarget() . '</info>');
        if (!is_dir($vendorPath = $this->package->getVendorAbsolutePath())) {
            throw new \RuntimeException(sprintf('Directory %s not properly installed, did you run "composer install" ?', $vendorPath));
        }

        $main = $this->getMain();
        $this->logger->info('Adding main package "' . $this->package->getName() . '"');

        // 使用绝对路径避免 WSL+NTFS 的 stat 缓存问题
        $fphar = $this->package->getDirectory() . $this->getTarget();
        $tphar = sprintf('%s.%s.phar', $fphar, mt_rand());

        // 查找需要打包的文件
        $finder = Finder::create()->files()->ignoreVCS(true)->notPath([
            '/^build/i',
            '/^bin\/start-watch$/i',
            '/^readme.md$/i',
            '/^phpstan.neon$/i',
            '/^phpunit\.xml$/i',
            '/^\.php-cs-fixer\.php$/i',
            '/^\.php-sfx-packer\.php$/i',
            '/^composer\.phar/i',
            '/^bin\/swoole-/i',
            $fphar,
        ])->exclude([$main, 'plugin', 'public', 'runtime', 'web', 'docs', 'tests', 'devtools', '.github', rtrim($this->package->getVendorPath(), '/')])->exclude($this->getMount())->in($this->package->getDirectory());

        // 创建 Phar 操作实例
        $customPhar = new Custom($tphar);
        $customPhar->stripPhpSources(self::PHP_SOURCE_STRIP_PATH_PREFIXES);
        $target = new Target($customPhar, $this);

        // 启动缓冲区并添加资源包
        $target->startBuffering();
        $target->addFromString('xadmin_obfuscate.php', $this->getObfuscationRuntimeCode());
        $target->addBundle($this->package->bundle($finder));

        // 前端资源以 zip 形式进入 Phar，运行时首次 start 或手动命令再发布到 public，避免 raw web/dist 目录暴露在包内。
        $this->addFrontendArchive($target);

        // 应用插件的清单与资源由 plugin.json 显式声明；主包默认排除了 plugin/，
        // 因此这里按清单补入 Phar，确保安装/迁移/翻译加载时可直接读取包内文件。
        $this->addPluginManifestResources($target);

        // 开启 ScanCacheable 功能
        $this->enableScanCacheable($target);

        // runtime 文件夹单独处理
        if (is_dir($this->package->getDirectory() . 'runtime')) {
            $this->logger->info('Adding runtime container files');
            $fcache = 'runtime/container/scan.cache';
            $finder = Finder::create()->files()->exclude($fcache)->in($this->package->getDirectory() . 'runtime/container');
            $target->addBundle($this->package->bundle($finder));
            $scanCache = unserialize(file_get_contents($fcache));
            $scanCache[1] = array_map(fn ($path) => $this->getPathLocalToBase($path), $scanCache[1]);
            $target->addFromString($fcache, serialize($scanCache));
        }

        // 添加 .env 文件
        if (!in_array('.env', $this->getMount()) && is_file($this->package->getDirectory() . '.env')) {
            $this->logger->info('Adding .env file');
            $target->addFile($this->package->getDirectory() . '.env');
        }

        // 添加 vendor/bin 文件
        if (is_dir($vendorPath . 'bin/')) {
            $this->logger->info('Adding vendor/bin files');
            $binIterator = new \GlobIterator($vendorPath . 'bin/*');
            while ($binIterator->valid()) {
                $target->addFile($binIterator->getPathname());
                $binIterator->next();
            }
        }

        $this->logger->info('Adding composer base files');
        $target->addFile($vendorPath . 'autoload.php');
        $target->buildFromIterator(new \GlobIterator($vendorPath . 'composer/*.*', \FilesystemIterator::KEY_AS_FILENAME));

        // 添加依赖包
        foreach ($this->getPackagesDependencies() as $package) {
            $this->logger->info('Adding dependency "' . $package->getName() . '" from "' . $this->getPathLocalToBase($package->getDirectory()) . '"');
            if (is_link(rtrim($package->getDirectory(), '/'))) {
                foreach ($package->bundle() as $resource) {
                    foreach ($resource as $iterator) {
                        $target->addFile($iterator->getPathname());
                    }
                }
            } else {
                $target->addBundle($package->bundle($this->createVendorFinder($package)));
            }
        }

        // 替换 ConfigFactory readPaths 方法
        $this->logger->info('Replace method "readPaths" in file "vendor/hyperf/config/src/ConfigFactory.php" and change "getRealPath" to "getPathname".');
        $this->replaceConfigFactoryReadPaths($target, $vendorPath);

        // 重写主文件挂载链接代码
        $this->logger->info('Adding main file "' . $main . '"');
        $this->rewriteMainWithMountLinkCode($target, $main);

        // 打包缓存文件
        $this->logger->info('Packaging all cache files into the Phar package.');
        $target->save();

        // 设置默认 stub
        $this->logger->info('Setting stub');
        $target->setStub($target->createDefaultStub($main));
        $this->logger->info('Setting default stub <info>' . $main . '</info>.');

        // 停止缓冲区
        $target->stopBuffering();
        $target->setSignatureAlgorithm(\Phar::SHA256);

        if (file_exists($fphar)) {
            $this->logger->info('Overwriting existing file <info>' . $target . '</info> (' . $this->getSize($fphar) . ')');
        }

        // 释放 Phar 文件句柄，避免 NTFS 文件锁导致 rename 失败；大小提前从临时 Phar 读取，
        // 避免 WSL/NTFS 下 rename 后目标路径短暂 stat 不可用影响构建日志。
        unset($target);

        $createdSize = $this->getSize($tphar);
        if (rename($tphar, $fphar) === false) {
            throw new \UnexpectedValueException(sprintf('Unable to rename temporary phar archive to %s', $fphar));
        }

        $this->logger->info(sprintf(
            '<info>OK</info> - Creating <info>%s (%s) completed after %s',
            $fphar,
            $createdSize,
            round(max(microtime(true) - $time, 0), 1) . 's'
        ));
    }

    /**
     * 为 vendor 依赖包创建过滤后的 Finder，排除测试、文档等非运行时文件.
     */
    protected function createVendorFinder(Package $package): Finder
    {
        return Finder::create()->files()->ignoreVCS(true)
            ->exclude([
                'tests',
                'test',
                'Tests',
                'Test',
                '.github',
                '.gitlab',
                '.circleci',
                'doc',
                'docs',
                'documentation',
                'examples',
                'example',
            ])
            ->notPath([
                '/^phpunit\.xml/i',
                '/^phpstan\.neon/i',
                '/^psalm\.xml/i',
                '/^\.php-cs-fixer/i',
                '/^\.editorconfig$/i',
                '/^\.styleci/i',
                '/^Makefile$/i',
                '/^Dockerfile/i',
                '/^docker-compose/i',
                '/^CHANGELOG/i',
                '/^UPGRADING/i',
                '/^CONTRIBUTING/i',
                '/^UPGRADE/i',
                '/^CHANGES/i',
            ])
            ->notName(['*.md', 'LICENSE', 'LICENSE.*', 'COPYING', 'COPYING.*'])
            ->in($package->getDirectory());
    }

    /**
     * 将 web/dist 压缩为 Phar 内唯一前端资源包。
     *
     * _app.config.js 由 SiteMiddleware 运行期动态生成，不随构建产物发布；zip 写入前校验 index.html，
     * 防止前端未构建或构建失败时仍打出不可访问的二进制包。
     */
    protected function addFrontendArchive(Target $targetPhar): void
    {
        $sourceDir = $this->package->getDirectory() . 'web/dist';
        if (!is_dir($sourceDir)) {
            throw new \RuntimeException('Frontend source directory missing: ' . $sourceDir);
        }

        $archive = $this->createFrontendArchive($sourceDir);
        try {
            $this->logger->info('Adding frontend archive "' . self::FRONTEND_ARCHIVE_PATH . '"');
            $content = file_get_contents($archive);
            if (!is_string($content) || $content === '') {
                throw new \RuntimeException('Read frontend archive failed: ' . $archive);
            }
            $targetPhar->addFromString(self::FRONTEND_ARCHIVE_PATH, $content);
        } finally {
            @unlink($archive);
        }
    }

    /**
     * 将 plugin.json 声明的应用插件资源写入 Phar 的 plugin/<Module>/ 路径。
     *
     * 运行时 PluginManifestRegistry 通过 syspath('plugin') 扫描包内清单，语言包和迁移目录也会直接
     * 以 phar:// 路径注册给翻译加载器与 Migrator，因此这些目录不能只依赖 Composer vendor 依赖路径。
     */
    protected function addPluginManifestResources(Target $targetPhar): void
    {
        $pluginRoot = $this->package->getDirectory() . 'plugin';
        if (!is_dir($pluginRoot)) {
            return;
        }

        foreach (new \DirectoryIterator($pluginRoot) as $plugin) {
            if ($plugin->isDot() || !$plugin->isDir()) {
                continue;
            }

            $manifestPath = str_replace('\\', '/', $plugin->getPathname()) . '/' . self::PLUGIN_MANIFEST_FILE;
            if (!is_file($manifestPath)) {
                continue;
            }

            $manifest = $this->loadJson($manifestPath);
            $this->logger->info('Adding application plugin manifest/resources "' . $plugin->getBasename() . '"');
            $targetPhar->addFile($manifestPath);

            foreach ($this->pluginResourceRoots($manifest, $manifestPath) as $resourceRoot) {
                $resourcePath = str_replace('\\', '/', $plugin->getPathname()) . '/' . $resourceRoot;
                if (!is_dir($resourcePath)) {
                    continue;
                }

                $this->logger->info('Adding application plugin resource "' . $this->getPathLocalToBase($resourcePath) . '"');
                $finder = Finder::create()->files()->ignoreVCS(true)->in($resourcePath);
                $targetPhar->addBundle($this->package->bundle($finder));
            }
        }
    }

    /**
     * 开启扫描缓存功能.
     */
    protected function enableScanCacheable(Target $targetPhar): void
    {
        $cfgPath = 'config/config.php';
        $absPath = $this->package->getDirectory() . $cfgPath;
        if (!file_exists($absPath)) {
            return;
        }
        $code = file_get_contents($absPath);
        $code = (new Ast())->parse($code, [new RewriteConfigVisitor()]);
        $targetPhar->addFromString($cfgPath, $code);
    }

    /**
     * 替换配置工厂的读取路径方法.
     */
    protected function replaceConfigFactoryReadPaths(Target $targetPhar, string $vendorPath): void
    {
        $cfgPath = 'hyperf/config/src/ConfigFactory.php';
        $absPath = $vendorPath . $cfgPath;
        if (!file_exists($absPath)) {
            return;
        }
        $code = file_get_contents($absPath);
        $code = (new Ast())->parse($code, [new RewriteConfigFactoryVisitor()]);
        $targetPhar->addFromString('vendor/' . $cfgPath, $code);
    }

    /**
     * 重写主文件挂载链接代码
     */
    protected function rewriteMainWithMountLinkCode(Target $targetPhar, string $mainPath): void
    {
        $code = file_get_contents($mainPath);
        $code = (new Ast())->parse($code, [new UnshiftCodeStringVisitor($this->getMountLinkCode())]);
        $targetPhar->addFromString($mainPath, $code);
    }

    /**
     * 创建前端 zip 包并排除运行期动态配置和本机元数据。
     */
    private function createFrontendArchive(string $sourceDir): string
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('ZipArchive extension is required to package frontend assets.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'xadmin-web-dist-');
        if ($tmp === false) {
            throw new \RuntimeException('Unable to create frontend archive temp file');
        }

        $zip = new \ZipArchive();
        if ($zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);
            throw new \RuntimeException('Unable to open frontend archive temp file');
        }

        $sourceDir = rtrim(str_replace('\\', '/', $sourceDir), '/');
        $entries = [];
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($iterator as $fileInfo) {
                /** @var \SplFileInfo $fileInfo */
                if (!$fileInfo->isFile()) {
                    continue;
                }

                $source = str_replace('\\', '/', $fileInfo->getPathname());
                $relative = ltrim(substr($source, strlen($sourceDir)), '/');
                if ($this->isFrontendArchiveExcluded($relative)) {
                    continue;
                }
                if (str_starts_with($relative, '/') || preg_match('/^[A-Za-z]:\//', $relative) === 1 || preg_match('#(^|/)\.\.(/|$)#', $relative) === 1) {
                    throw new \RuntimeException('Frontend archive contains unsafe path: ' . $relative);
                }
                if (!$zip->addFile($fileInfo->getPathname(), $relative)) {
                    throw new \RuntimeException('Unable to add frontend file to archive: ' . $relative);
                }
                $zip->setCompressionName($relative, \ZipArchive::CM_DEFLATE, 6);
                $entries[$relative] = true;
            }
        } finally {
            $zip->close();
        }

        foreach (self::FRONTEND_REQUIRED_ENTRIES as $entry) {
            if (!isset($entries[$entry])) {
                @unlink($tmp);
                throw new \RuntimeException('Frontend archive missing required entry: ' . $entry);
            }
        }

        return $tmp;
    }

    /**
     * 判断前端文件是否不应进入发布包。
     */
    private function isFrontendArchiveExcluded(string $relative): bool
    {
        $relative = trim(str_replace('\\', '/', $relative), '/');
        return basename($relative) === '.DS_Store' || in_array($relative, self::FRONTEND_DYNAMIC_CONFIGS, true);
    }

    /**
     * 解析应用插件清单中显式启用的资源目录。
     *
     * @param array<string,mixed> $manifest
     * @return array<int,string>
     */
    private function pluginResourceRoots(array $manifest, string $manifestPath): array
    {
        $pluginMeta = is_array($manifest['plugin'] ?? null) ? $manifest['plugin'] : [];
        $roots = [];
        foreach (self::PLUGIN_RESOURCE_FIELDS as $field) {
            $value = $pluginMeta[$field] ?? null;
            if (!is_string($value) || trim($value) === '') {
                continue;
            }

            $roots[] = $this->normalizePluginResourcePath($value, $manifestPath, 'plugin.' . $field);
        }

        $roots = array_values(array_unique($roots));
        sort($roots);

        return $roots;
    }

    /**
     * 规范化 plugin.json 中的插件资源路径，禁止绝对路径和上级目录逃逸。
     */
    private function normalizePluginResourcePath(string $path, string $manifestPath, string $field): string
    {
        $raw = trim($path);
        if (
            $raw === ''
            || str_starts_with($raw, '/')
            || preg_match('/^[A-Za-z]:[\/\\\]/', $raw) === 1
        ) {
            throw new \RuntimeException(sprintf('%s 的 %s 必须是插件目录内的相对路径。', $this->getPathLocalToBase($manifestPath), $field));
        }

        $parts = [];
        foreach (explode('/', str_replace('\\', '/', $raw)) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                throw new \RuntimeException(sprintf('%s 的 %s 不允许包含上级目录。', $this->getPathLocalToBase($manifestPath), $field));
            }
            $parts[] = $part;
        }

        if ($parts === []) {
            throw new \RuntimeException(sprintf('%s 的 %s 不能为空。', $this->getPathLocalToBase($manifestPath), $field));
        }

        return implode('/', $parts);
    }

    /**
     * 加载 JSON 文件.
     */
    private function loadJson(string $path): array
    {
        try {
            $result = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException(sprintf('Unable to parse given path %s', $path), $e->getCode(), $e);
        }
        return $result;
    }

    /**
     * 获取文件大小，单位 KiB.
     */
    private function getSize(Builder|string $path): string
    {
        $path = (string)$path;
        // WSL/NTFS 下 Phar rename 后可能短暂无法 stat；文件实际已落盘时，日志大小读取不能中断发布构建。
        for ($retry = 0; $retry < 5; ++$retry) {
            clearstatcache(true, $path);
            $size = @filesize($path);
            if ($size !== false) {
                return round($size / 1024, 1) . ' KiB';
            }
            usleep(100000);
        }

        return 'unknown KiB';
    }
}
