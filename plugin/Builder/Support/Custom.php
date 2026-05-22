<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://github.com/zoujingli/SmartAdmin/blob/master/readme.md
 */

namespace Builder\Support;

use Builder\Ast\Ast;
use Builder\Ast\Visitor\ObfuscateFunctionBodyVisitor;

/**
 * 自定义 PHAR 构建工具.
 *
 * 支持：
 * - 添加单个文件或字符串内容
 * - 从目录或迭代器批量添加文件
 * - 临时目录缓存，构建完成后自动清理
 */
class Custom extends \Phar
{
    /** @var string 临时目录路径 */
    private string $tempDir;

    /**
     * 是否对指定一方 PHP 源码启用构建期加固。
     */
    private bool $stripPhpSources = false;

    /**
     * 允许执行源码加固的 PHAR 内部路径前缀。
     *
     * @var string[]
     */
    private array $phpStripPathPrefixes = [];

    /**
     * 构建期 AST 解析器复用实例；Custom 在单次打包过程中串行写入，可安全复用。
     */
    private ?Ast $ast = null;

    /**
     * PHP 源码加固缓存目录，避免重复构建时反复解析同一文件。
     */
    private ?string $phpStripCacheDir = null;

    /**
     * 当前加固算法指纹，绑定 PHP 版本和混淆器源码。
     */
    private ?string $phpStripCacheVersion = null;

    /**
     * 构造函数.
     *
     * @param string $filename 输出 PHAR 文件名
     * @param int $flags 文件迭代器标志
     * @param null|string $alias 可选别名
     */
    public function __construct(string $filename, int $flags = \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS, ?string $alias = null)
    {
        parent::__construct($filename, $flags, $alias);

        // 临时目录
        $this->tempDir = sys_get_temp_dir() . '/phar_cache_' . uniqid();
        $this->createDirectory($this->tempDir);
    }

    /**
     * 开启 PHP 源码加固，仅对指定 PHAR 内部路径前缀生效。
     *
     * @param string[] $pathPrefixes
     */
    public function stripPhpSources(array $pathPrefixes): static
    {
        $this->stripPhpSources = true;
        $this->phpStripPathPrefixes = array_values(array_filter(array_map(
            static fn (string $prefix): string => rtrim(ltrim(str_replace('\\', '/', $prefix), '/'), '/') . '/',
            $pathPrefixes
        )));

        return $this;
    }

    /**
     * 添加单个文件到临时目录.
     *
     * @param string $filename 源文件路径
     * @param null|string $localName PHAR 内路径
     */
    public function addFile(string $filename, ?string $localName = null): void
    {
        $this->saveFile($filename, $localName ?? basename($filename));
    }

    /**
     * 添加字符串内容作为文件到临时目录.
     *
     * @param string $localName PHAR 内路径
     * @param string $contents 文件内容
     */
    public function addFromString(string $localName, string $contents): void
    {
        $this->saveFileContent($localName, $contents);
    }

    /**
     * 从目录递归添加文件.
     *
     * @param string $directory 源目录
     * @param null|string $pattern 可选正则匹配
     * @return array 返回添加的文件列表（空数组）
     */
    public function buildFromDirectory(string $directory, ?string $pattern = null): array
    {
        $this->recursiveCopy($directory, $this->tempDir, $pattern);
        return [];
    }

    /**
     * 从迭代器添加文件.
     *
     * @param iterable $iterator \SplFileInfo 对象迭代器
     * @param null|string $baseDirectory 基础目录，用于计算相对路径
     * @return array 返回添加的文件列表（空数组）
     */
    public function buildFromIterator($iterator, ?string $baseDirectory = null): array
    {
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof \SplFileInfo) {
                continue;
            }

            $source = $fileInfo->getRealPath() ?: $fileInfo->getPathname();
            if (!is_string($source) || $source === '' || !is_file($source)) {
                continue;
            }

            // PHAR 内部路径必须稳定使用项目相对路径；Windows 下 getRealPath 会返回反斜杠，
            // 这里同时兼容 Finder 路径和真实路径，避免插件资源被写成绝对盘符路径。
            $this->addFile($source, $this->resolveIteratorLocalPath($fileInfo, $source, $baseDirectory));
        }
        return [];
    }

    /**
     * 保存 PHAR 文件并清理临时目录.
     */
    public function save(): void
    {
        parent::buildFromDirectory($this->tempDir);
        $this->clearTempDir();
    }

    /**
     * 递归复制目录到临时目录.
     *
     * @param string $source 源目录
     * @param string $destination 目标目录
     * @param null|string $pattern 文件名正则匹配
     */
    private function recursiveCopy(string $source, string $destination, ?string $pattern = null): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $targetPath = $destination . '/' . substr($item->getRealPath(), strlen($source) + 1);
            if ($item->isDir()) {
                $this->createDirectory($targetPath);
            } elseif (!$pattern || preg_match($pattern, $item->getFilename())) {
                $localName = ltrim(substr($targetPath, strlen($this->tempDir)), '/');
                $this->saveFile($item->getRealPath(), $localName);
            }
        }
    }

    /**
     * 创建目录（递归）.
     *
     * @param string $dir 目录路径
     */
    private function createDirectory(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \RuntimeException("Directory {$dir} was not created");
        }
    }

    /**
     * 临时目录文件清理.
     */
    private function clearTempDir(): void
    {
        if (!is_dir($this->tempDir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tempDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $fileinfo->isDir() ? rmdir($fileinfo->getRealPath()) : unlink($fileinfo->getRealPath());
        }

        rmdir($this->tempDir);
    }

    /**
     * 保存单个文件到临时目录（内部使用）.
     *
     * @param string $source 源文件
     * @param string $localName 临时目录相对路径
     */
    private function saveFile(string $source, string $localName): void
    {
        $relativePath = $this->tempDir . '/' . $localName;
        $this->createDirectory(dirname($relativePath));
        if ($this->shouldStripPhpSource($localName)) {
            file_put_contents($relativePath, $this->stripPhpSource($source, $localName));
            return;
        }
        copy($source, $relativePath);
    }

    /**
     * 保存字符串内容为文件到临时目录（内部使用）.
     *
     * @param string $localName 文件路径
     * @param string $contents 文件内容
     */
    private function saveFileContent(string $localName, string $contents): void
    {
        $relativePath = $this->tempDir . '/' . $localName;
        $this->createDirectory(dirname($relativePath));
        if ($this->shouldStripPhpSource($localName)) {
            // addFromString 常用于写入 AST 改写后的入口和配置，同样需要遵守加固和注解保护边界。
            $contents = $this->stripPhpSource($localName, $localName, $contents);
        }
        file_put_contents($relativePath, $contents);
    }

    /**
     * 判断当前文件是否属于可安全加固的一方 PHP 源码范围。
     */
    private function shouldStripPhpSource(string $localName): bool
    {
        if (!$this->stripPhpSources) {
            return false;
        }

        $localName = ltrim(str_replace('\\', '/', $localName), '/');
        if (!str_ends_with($localName, '.php')) {
            return false;
        }

        foreach ($this->phpStripPathPrefixes as $prefix) {
            if (str_starts_with($localName, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 加固 PHP 源码；若发现会被 php_strip_whitespace 删除的 DocBlock 运行时注解则中止。
     */
    private function stripPhpSource(string $filename, string $localName, ?string $contents = null): string
    {
        $cacheFile = $contents === null ? $this->getPhpStripFileCacheFile($filename, $localName) : null;
        if ($cacheFile !== null && is_file($cacheFile)) {
            $cached = file_get_contents($cacheFile);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }

        $source = $contents ?? file_get_contents($filename);
        if (!is_string($source)) {
            throw new \RuntimeException("Read source file {$filename} failed");
        }
        if ($this->containsRuntimeDocblockAnnotation($source)) {
            throw new \RuntimeException("打包加固不能压缩包含 DocBlock 运行时注解的 PHP 文件：{$localName}");
        }

        $cacheFile ??= $this->getPhpStripContentCacheFile($localName, $source);
        if (is_file($cacheFile)) {
            $cached = file_get_contents($cacheFile);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }

        $source = $this->getAst()->parse($source, [new ObfuscateFunctionBodyVisitor($localName)]);

        $tempFile = tempnam(sys_get_temp_dir(), 'xadmin_strip_');
        if ($tempFile === false) {
            throw new \RuntimeException("Create strip temp file for {$localName} failed");
        }
        file_put_contents($tempFile, $source);

        try {
            $stripped = php_strip_whitespace($tempFile);
        } finally {
            @unlink($tempFile);
        }
        if ($stripped === '') {
            throw new \RuntimeException("Strip PHP source file {$filename} failed");
        }

        $this->writePhpStripCache($cacheFile, $stripped);
        return $stripped;
    }

    /**
     * 获取复用的 AST 处理器。
     */
    private function getAst(): Ast
    {
        return $this->ast ??= new Ast();
    }

    /**
     * 生成文件源码加固缓存路径；文件路径、大小、mtime、ctime 均参与指纹。
     */
    private function getPhpStripFileCacheFile(string $filename, string $localName): ?string
    {
        $realpath = realpath($filename) ?: $filename;
        $size = filesize($filename);
        $mtime = filemtime($filename);
        $ctime = filectime($filename);
        if ($size === false || $mtime === false || $ctime === false) {
            return null;
        }

        return $this->getPhpStripCacheFile($localName, $realpath . "\0" . $size . "\0" . $mtime . "\0" . $ctime);
    }

    /**
     * 生成字符串源码加固缓存路径；addFromString 没有稳定文件元数据，只能按内容指纹缓存。
     */
    private function getPhpStripContentCacheFile(string $localName, string $source): string
    {
        return $this->getPhpStripCacheFile($localName, sha1($source));
    }

    /**
     * 生成源码加固缓存路径；缓存放在系统临时目录，不进入 Phar，也不污染发布目录。
     */
    private function getPhpStripCacheFile(string $localName, string $fingerprint): string
    {
        $hash = sha1($this->getPhpStripCacheVersion() . "\0" . $localName . "\0" . $fingerprint);
        $dir = $this->getPhpStripCacheDir() . '/' . substr($hash, 0, 2);
        $this->createDirectory($dir);

        return $dir . '/' . $hash . '.php';
    }

    /**
     * 写入缓存采用临时文件 + rename，避免异常退出留下半截缓存。
     */
    private function writePhpStripCache(string $cacheFile, string $contents): void
    {
        $dir = dirname($cacheFile);
        $tmp = tempnam($dir, 'put_');
        if ($tmp === false) {
            return;
        }
        if (file_put_contents($tmp, $contents, LOCK_EX) === false || !@rename($tmp, $cacheFile)) {
            @unlink($tmp);
        }
    }

    /**
     * 每个项目独立缓存目录，避免不同工作区同名 PHAR 内路径互相影响。
     */
    private function getPhpStripCacheDir(): string
    {
        if ($this->phpStripCacheDir !== null) {
            return $this->phpStripCacheDir;
        }

        $root = getcwd() ?: __DIR__;
        $this->phpStripCacheDir = rtrim(sys_get_temp_dir(), '/') . '/xadmin_php_strip_cache/' . substr(sha1($root), 0, 16);
        $this->createDirectory($this->phpStripCacheDir);

        return $this->phpStripCacheDir;
    }

    /**
     * 缓存版本绑定混淆器源码和 PHP 版本，后续调整加固规则时不会误用旧结果。
     */
    private function getPhpStripCacheVersion(): string
    {
        if ($this->phpStripCacheVersion !== null) {
            return $this->phpStripCacheVersion;
        }

        $visitor = dirname(__DIR__) . '/Ast/Visitor/ObfuscateFunctionBodyVisitor.php';
        $visitorHash = is_file($visitor) ? (sha1_file($visitor) ?: 'missing') : 'missing';

        return $this->phpStripCacheVersion = 'php-strip-v1:' . PHP_VERSION_ID . ':' . $visitorHash;
    }

    /**
     * 检测会被 php_strip_whitespace 删除的 DocBlock 运行时注解，避免旧式注解项目被误压缩后失效。
     */
    private function containsRuntimeDocblockAnnotation(string $source): bool
    {
        if (!preg_match_all('#/\*\*.*?\*/#s', $source, $matches)) {
            return false;
        }

        $annotationNames = [
            'Aspect',
            'Auth',
            'AutoController',
            'Cacheable',
            'Clear',
            'Command',
            'Controller',
            'Crontab',
            'DeleteMapping',
            'GetMapping',
            'Inject',
            'Listener',
            'Logger',
            'Middleware',
            'Permission',
            'PostMapping',
            'Process',
            'PutMapping',
            'RequestMapping',
            'Transaction',
        ];
        $pattern = '/@(?:' . implode('|', $annotationNames) . ')(?:\s|\()/';
        foreach ($matches[0] as $docblock) {
            if (preg_match($pattern, $docblock)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 计算迭代器文件在 PHAR 内的相对路径。
     *
     * @param \SplFileInfo $fileInfo 迭代器文件对象
     * @param string $source 实际可读取的源文件
     * @param null|string $baseDirectory 项目根目录，用于裁剪 PHAR 内部路径
     */
    private function resolveIteratorLocalPath(\SplFileInfo $fileInfo, string $source, ?string $baseDirectory): string
    {
        if ($baseDirectory !== null && $baseDirectory !== '') {
            $base = rtrim(str_replace('\\', '/', $baseDirectory), '/') . '/';
            foreach ([$fileInfo->getPathname(), $source] as $candidate) {
                $path = str_replace('\\', '/', (string)$candidate);
                if (str_starts_with($path, $base)) {
                    return ltrim(substr($path, strlen($base)), '/');
                }
            }
        }

        if (method_exists($fileInfo, 'getRelativePathname')) {
            $relative = str_replace('\\', '/', (string)$fileInfo->getRelativePathname());
            if ($relative !== '' && !preg_match('/^[A-Za-z]:\//', $relative)) {
                return ltrim($relative, '/');
            }
        }

        return $fileInfo->getFilename();
    }
}
