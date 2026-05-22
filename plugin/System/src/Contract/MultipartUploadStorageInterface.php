<?php

declare(strict_types=1);

namespace System\Contract;

interface MultipartUploadStorageInterface
{
    /**
     * @return array<string, mixed>
     */
    public function initiateMultipartUpload(string $name, string $mimeType, ?string $downloadName = null, int $expires = 3600): array;

    /**
     * @return array<string, mixed>
     */
    public function signMultipartPart(string $name, string $uploadId, int $partNumber, int $expires = 3600): array;

    /**
     * @param array<int, array{PartNumber:int,ETag:string}> $parts
     * @return array<string, mixed>
     */
    public function completeMultipartUpload(string $name, string $uploadId, array $parts): array;

    /**
     * 终止分片上传会话。
     */
    public function abortMultipartUpload(string $name, string $uploadId): bool;
}
