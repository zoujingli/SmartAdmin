<?php

declare(strict_types=1);

namespace System\Contract;

interface StorageInterface
{
    /**
     * @return array<int, array<string, string>>
     */
    public static function region(): array;

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function set(string $name, string $file, bool $safe = false, ?string $attname = null, array $options = []): array;

    /**
     * 读取对象内容。
     */
    public function get(string $name, bool $safe = false): string;

    /**
     * 删除对象。
     */
    public function del(string $name, bool $safe = false): bool;

    /**
     * 判断对象是否存在。
     */
    public function has(string $name, bool $safe = false): bool;

    /**
     * 生成对象访问地址。
     */
    public function url(string $name, bool $safe = false, ?string $attname = null): string;

    /**
     * 获取对象物理路径或可解析路径。
     */
    public function path(string $name, bool $safe = false): string;

    /**
     * @return array<string, mixed>
     */
    public function info(string $name, bool $safe = false, ?string $attname = null): array;

    /**
     * @param array<string, mixed> $context
     * 支持的上下文字段：
     * - name: 对象键
     * - mime_type: 文件 MIME
     * - expires: 签名有效期
     * - download_name: 默认下载文件名
     * @return array<string, mixed>
     */
    public function upload(array $context = []): array;
}
