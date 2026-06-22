<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace System\Contract;

interface FilePathUploadStorageInterface
{
    /**
     * 从本地文件路径写入对象。
     *
     * relay-chunk 完成后文件已经在服务端磁盘合并，直接使用路径写入可以避免把大文件整包读入 PHP 内存。
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function setFile(string $name, string $path, bool $safe = false, ?string $attname = null, array $options = []): array;
}
