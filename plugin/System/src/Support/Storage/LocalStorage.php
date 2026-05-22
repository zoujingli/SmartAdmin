<?php

declare(strict_types=1);

namespace System\Support\Storage;

use Library\Exception\ErrorResponseException;
use Library\Helper\RequestHelper;
use System\Support\UploadDriver;

final class LocalStorage extends AbstractStorage
{
    /**
     * @return array<int, array<string, string>>
     */
    public static function region(): array
    {
        return [];
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function set(string $name, string $file, bool $safe = false, ?string $attname = null, array $options = []): array
    {
        $filePath = $this->absolutePath($name);
        $dir = dirname($filePath);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new ErrorResponseException('无法创建本地存储目录');
        }

        if (file_put_contents($filePath, $file) === false) {
            throw new ErrorResponseException('写入本地文件失败');
        }

        return $this->info($name);
    }

    /**
     * 读取本地对象内容。
     */
    public function get(string $name, bool $safe = false): string
    {
        $filePath = $this->absolutePath($name);
        if (!is_file($filePath)) {
            throw new ErrorResponseException('文件不存在');
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new ErrorResponseException('读取本地文件失败');
        }

        return $content;
    }

    /**
     * 删除本地对象。
     */
    public function del(string $name, bool $safe = false): bool
    {
        $filePath = $this->absolutePath($name);
        return !is_file($filePath) || @unlink($filePath);
    }

    /**
     * 判断本地对象是否存在。
     */
    public function has(string $name, bool $safe = false): bool
    {
        return is_file($this->absolutePath($name));
    }

    /**
     * 生成本地对象访问地址。
     */
    public function url(string $name, bool $safe = false, ?string $attname = null): string
    {
        $key = $this->normalizeKey($name);
        if ($this->linkType() === UploadDriver::LINK_TYPE_STORAGE_PATH) {
            return $key;
        }

        $relative = trim(trim((string)($this->config['storage_path'] ?? 'upload'), '/') . '/' . $key, '/');
        if ($this->linkType() === UploadDriver::LINK_TYPE_RELATIVE_URL) {
            $url = '/' . $relative;
        } else {
            $domain = UploadDriver::normalizeDomain((string)($this->config['domain'] ?? ''));
            if ($domain !== '') {
                $url = $this->protocolPrefix() . $domain . '/' . $relative;
            } else {
                $origin = RequestHelper::getOrigin();
                $url = $origin !== null ? rtrim($origin, '/') . '/' . $relative : '/' . $relative;
            }
        }

        if ($attname === null || trim($attname) === '') {
            return $url;
        }

        return $this->appendQuery($url, [
            'attname' => $this->normalizeAttachmentName($attname, basename($key)),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function info(string $name, bool $safe = false, ?string $attname = null): array
    {
        $filePath = $this->absolutePath($name);
        if (!is_file($filePath)) {
            throw new ErrorResponseException('文件不存在');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($filePath);

        return $this->buildInfo($this->normalizeKey($name), [
            'mime_type' => is_string($mimeType) && $mimeType !== '' ? $mimeType : 'application/octet-stream',
            'size_byte' => (int)(filesize($filePath) ?: 0),
            'last_modified' => date('Y-m-d H:i:s', filemtime($filePath) ?: time()),
        ]);
    }

    /**
     * 解析对象对应的本地绝对路径。
     */
    private function absolutePath(string $name): string
    {
        $storagePath = trim((string)($this->config['storage_path'] ?? 'upload'), '/');
        $root = rtrim((string)($this->config['root'] ?? runpath('public/' . $storagePath)), '/');
        return $root . '/' . $this->normalizeKey($name);
    }
}
