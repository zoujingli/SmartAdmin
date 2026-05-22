<?php

declare(strict_types=1);

namespace System\Support\Storage;

use System\Contract\StorageInterface;
use System\Support\UploadDriver;

abstract class AbstractStorage implements StorageInterface
{
    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $common
     */
    public function __construct(
        protected array $config,
        protected array $common = [],
    ) {}

    /**
     * 返回对象标准路径表示。
     */
    public function path(string $name, bool $safe = false): string
    {
        return $this->normalizeKey($name);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function upload(array $context = []): array
    {
        return ['supported' => false];
    }

    /**
     * 规范化对象键。
     */
    protected function normalizeKey(string $name): string
    {
        return UploadDriver::normalizePath($name);
    }

    /**
     * 解析链接协议前缀。
     */
    protected function protocolPrefix(): string
    {
        return match (UploadDriver::normalizeProtocol((string)($this->common['protocol'] ?? UploadDriver::PROTOCOL_HTTPS))) {
            UploadDriver::PROTOCOL_HTTP => 'http://',
            UploadDriver::PROTOCOL_AUTO => '//',
            default => 'https://',
        };
    }

    /**
     * 解析链接输出类型。
     */
    protected function linkType(): string
    {
        return UploadDriver::normalizeLinkType((string)($this->common['link_type'] ?? UploadDriver::LINK_TYPE_FULL_URL));
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    protected function buildInfo(string $key, array $meta = []): array
    {
        return [
            'path' => $key,
            'url' => $this->url($key),
            'mime_type' => (string)($meta['mime_type'] ?? 'application/octet-stream'),
            'size_byte' => (int)($meta['size_byte'] ?? 0),
            'etag' => (string)($meta['etag'] ?? ''),
            'hash' => (string)($meta['hash'] ?? ''),
            'last_modified' => (string)($meta['last_modified'] ?? ''),
        ];
    }

    /**
     * 规范化下载文件名。
     */
    protected function normalizeAttachmentName(?string $attname, string $fallback = 'download'): string
    {
        $name = trim(str_replace(["\0", "\r", "\n"], '', (string)$attname));
        $name = basename(str_replace('\\', '/', $name));
        if ($name !== '') {
            return $name;
        }

        $fallback = basename(str_replace('\\', '/', trim($fallback)));
        return $fallback !== '' ? $fallback : 'download';
    }

    /**
     * @param array<string, scalar|null> $query
     */
    protected function appendQuery(string $url, array $query): string
    {
        $pairs = [];
        foreach ($query as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $pairs[] = rawurlencode($key) . '=' . rawurlencode((string)$value);
        }

        if ($pairs === []) {
            return $url;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . implode('&', $pairs);
    }

    /**
     * 生成附件下载头中的 Content-Disposition。
     */
    protected function buildAttachmentDisposition(string $attname): string
    {
        $name = $this->normalizeAttachmentName($attname);
        $ascii = preg_replace('/[^A-Za-z0-9!#$&+.^_`|~-]+/', '_', $name) ?: 'download';
        $ascii = trim($ascii, '._-') !== '' ? $ascii : 'download';

        return sprintf(
            'attachment; filename="%s"; filename*=UTF-8\'\'%s',
            addcslashes($ascii, "\\\""),
            rawurlencode($name)
        );
    }

}
