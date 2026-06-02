<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace System\Service;

use Hyperf\Database\Model\Builder;
use Hyperf\HttpMessage\Upload\UploadedFile;
use Library\Events\Processor\ScopeProcessor;
use Library\Exception\ErrorResponseException;
use Library\Exception\NotAllowResponseException;
use Library\Exception\UnauthorizedResponseException;
use Library\Helper\CoderHelper;
use Library\Interfaces\UserModelInterface;
use Library\Support\TenantContext;
use Library\Support\TenantUserResolver;
use System\Contract\MultipartUploadStorageInterface;
use System\Model\SystemFile;
use System\Support\UploadDriver;

/**
 * 上传运行时服务。
 *
 * 使用缓存维护上传会话，并通过标准存储接口统一接入多通道。
 */
final class FileUploadService
{
    /**
     * @param UploadConfigService $config 上传配置服务
     * @param FileService $files 文件服务
     * @param StorageManager $storageManager 存储驱动管理器
     * @param UploadSessionStore $sessions 上传会话存储
     */
    public function __construct(
        private UploadConfigService $config,
        private FileService $files,
        private StorageManager $storageManager,
        private UploadSessionStore $sessions,
    ) {}

    /**
     * 预检上传请求并创建上传会话。
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function prepare(array $payload): array
    {
        $originName = trim((string)($payload['name'] ?? ''));
        $size = (int)($payload['size'] ?? 0);
        $mimeType = trim((string)($payload['mime_type'] ?? $payload['type'] ?? 'application/octet-stream'));
        $hash = strtolower(trim((string)($payload['hash'] ?? '')));
        $principal = $this->currentUploadPrincipal();

        if ($originName === '' || $size <= 0) {
            throw new ErrorResponseException('上传文件元数据不完整');
        }

        $suffix = strtolower(pathinfo($originName, PATHINFO_EXTENSION));
        if ($suffix === '') {
            throw new ErrorResponseException('上传文件缺少后缀名');
        }

        $common = $this->config->getCommonConfig();
        $allowedExtensions = UploadDriver::allowedExtensions($common);
        if (!in_array($suffix, $allowedExtensions, true)) {
            throw new ErrorResponseException(sprintf('当前配置不允许上传 .%s 文件', $suffix));
        }

        $category = UploadDriver::resolveCategory((string)($payload['mode'] ?? $payload['scene'] ?? ''), $mimeType, $suffix);
        if (!UploadDriver::isCategoryExtensionAllowed($category, $suffix)) {
            throw new ErrorResponseException(sprintf('%s 类型不允许上传 .%s 文件', $category, $suffix));
        }

        $this->ensureMimeTypeAllowed($category, $mimeType, $suffix);
        $this->ensureExpectedSizeAllowed($size, $common);

        $driver = $this->config->resolveUploadMode((string)($payload['driver'] ?? ''));
        $nameType = UploadDriver::normalizeNameType((string)($common['name_type'] ?? UploadDriver::NAME_TYPE_HASH));
        [$storagePath, $objectName] = $this->makeObjectLocation($originName, $suffix, $hash, $nameType);

        if ($nameType === UploadDriver::NAME_TYPE_HASH) {
            $instantAsset = $this->tryFastUpload($driver, $hash, $originName, $category, $principal);
            if ($instantAsset !== null) {
                return [
                    'completed' => true,
                    'transport' => 'instant',
                    'asset' => $instantAsset,
                ];
            }
        }

        $transport = $this->resolveTransport($driver, $common, $size, (string)($payload['upload_type'] ?? ''));
        $partSizeBytes = $this->resolvePartSizeBytes($transport, $common);
        $partCount = (int)max(1, ceil($size / $partSizeBytes));

        $session = [
            'session_id' => CoderHelper::uuid(),
            'driver' => $driver,
            'transport' => $transport,
            'status' => 'initialized',
            'origin_name' => $originName,
            'scene' => $category,
            'suffix' => $suffix,
            'mime_type' => $mimeType,
            'size_byte' => $size,
            'hash' => $hash,
            'object_name' => $objectName,
            'storage_path' => $storagePath,
            'part_size' => $partSizeBytes,
            'part_count' => $partCount,
            'parts' => [],
            'upload_id' => '',
            'complete_token' => bin2hex(random_bytes(16)),
            'expires_at' => time() + 3600,
            'tenant_id' => $principal['tenant_id'],
            'auth_user_model' => $principal['auth_user_model'],
            'created_by' => $principal['uid'],
        ];

        $response = [
            'completed' => false,
            'upload_session_id' => $session['session_id'],
            'transport' => $transport,
            'driver' => $driver,
            'part_size' => $partSizeBytes,
            'part_count' => $partCount,
            'complete_token' => $session['complete_token'],
        ];

        $storage = $this->storageManager->driver($driver);
        $objectKey = $this->objectKey($storagePath, $objectName);

        if ($transport === UploadDriver::TRANSPORT_DIRECT_SINGLE) {
            $signed = $storage->upload([
                'download_name' => $originName,
                'name' => $objectKey,
                'mime_type' => $mimeType,
                'expires' => 3600,
            ]);
            if (!(bool)($signed['supported'] ?? false)) {
                // 驱动声明支持直传但运行时签名失败时，退回服务端中转；同一会话继续绑定原租户和文件元数据。
                $session['transport'] = $transport = UploadDriver::TRANSPORT_RELAY_SINGLE;
                $response['transport'] = $transport;
            } else {
                $response = array_merge($response, $signed);
            }
        }

        if ($transport === UploadDriver::TRANSPORT_DIRECT_MULTIPART) {
            if (!$storage instanceof MultipartUploadStorageInterface) {
                // 分片直传需要驱动同时实现分片接口；未实现时改用服务端分块，避免前端拿到不可完成的上传会话。
                $session['transport'] = $transport = UploadDriver::TRANSPORT_RELAY_CHUNK;
                $response['transport'] = $transport;
                $response['part_size'] = $partSizeBytes = $this->resolvePartSizeBytes($transport, $common);
                $response['part_count'] = $partCount = (int)max(1, ceil($size / $partSizeBytes));
                $session['part_size'] = $partSizeBytes;
                $session['part_count'] = $partCount;
            } else {
                $multipart = $storage->initiateMultipartUpload($objectKey, $mimeType, $originName, 3600);
                $session['upload_id'] = (string)($multipart['upload_id'] ?? '');
                $session['status'] = 'uploading';
            }
        }

        $this->sessions->put($session);

        return $response;
    }

    /**
     * 处理服务端中转单文件上传。
     *
     * @return array<string, mixed>
     */
    public function handleRelayUpload(string $sessionId, UploadedFile $file): array
    {
        // 浏览器直传可能被 OSS/COS Bucket CORS 策略拦截；同一会话允许退回服务端中转，仍复用预检阶段的租户与文件元数据。
        $session = $this->getSession($sessionId, [UploadDriver::TRANSPORT_RELAY_SINGLE, UploadDriver::TRANSPORT_DIRECT_SINGLE]);
        $tempPath = $file->getPathname();
        if (!is_file($tempPath)) {
            throw new ErrorResponseException('上传文件不存在');
        }

        $content = file_get_contents($tempPath);
        if ($content === false) {
            throw new ErrorResponseException('读取上传文件失败');
        }

        $mimeType = $this->detectMimeType($tempPath);
        $sizeByte = (int)(filesize($tempPath) ?: 0);
        $hash = md5_file($tempPath) ?: '';

        return $this->persistUploadedAsset($session, $content, $mimeType, $sizeByte, $hash);
    }

    /**
     * 处理服务端中转分块上传并在完成后合并入库。
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function handleRelayChunkUpload(array $payload, UploadedFile $file): array
    {
        $session = $this->getSession((string)($payload['upload_session_id'] ?? ''), [UploadDriver::TRANSPORT_RELAY_CHUNK]);
        $chunkIndex = (int)($payload['chunk_index'] ?? 0);
        $totalChunks = (int)($payload['total_chunks'] ?? 0);
        if ($chunkIndex < 1 || $totalChunks < 1) {
            throw new ErrorResponseException('分块参数无效');
        }
        if ($totalChunks !== (int)$session['part_count']) {
            throw new ErrorResponseException('分块总数与上传会话不一致');
        }

        $chunkDir = $this->chunkDir((string)$session['session_id']);
        is_dir($chunkDir) || mkdir($chunkDir, 0777, true);
        $chunkPath = $chunkDir . '/' . $chunkIndex . '.part';
        $file->moveTo($chunkPath);

        $parts = is_array($session['parts'] ?? null) ? $session['parts'] : [];
        $parts[(string)$chunkIndex] = [
            'index' => $chunkIndex,
            'size' => filesize($chunkPath) ?: 0,
        ];
        $session['parts'] = $parts;
        $session['status'] = 'uploading';
        $this->sessions->put($session);

        if (count($parts) < (int)$session['part_count']) {
            return [
                'completed' => false,
                'upload_session_id' => $session['session_id'],
                'uploaded_parts' => count($parts),
                'total_parts' => (int)$session['part_count'],
            ];
        }

        $mergedPath = $this->mergedFilePath((string)$session['session_id']);
        $target = fopen($mergedPath, 'wb');
        if ($target === false) {
            throw new ErrorResponseException('无法创建分块合并文件');
        }

        try {
            for ($index = 1; $index <= $totalChunks; ++$index) {
                $sourcePath = $chunkDir . '/' . $index . '.part';
                if (!is_file($sourcePath)) {
                    throw new ErrorResponseException(sprintf('缺少第 %d 个分块', $index));
                }

                $source = fopen($sourcePath, 'rb');
                if ($source === false) {
                    throw new ErrorResponseException(sprintf('无法读取第 %d 个分块', $index));
                }

                stream_copy_to_stream($source, $target);
                fclose($source);
            }
        } finally {
            // 合并失败也必须关闭目标句柄，后续 catch 会清理会话和临时目录。
            fclose($target);
        }

        $content = file_get_contents($mergedPath);
        if ($content === false) {
            throw new ErrorResponseException('读取合并文件失败');
        }

        try {
            $asset = $this->persistUploadedAsset(
                $session,
                $content,
                $this->detectMimeType($mergedPath),
                (int)(filesize($mergedPath) ?: 0),
                md5_file($mergedPath) ?: ''
            );
            $this->clearChunkDir($chunkDir);
            @unlink($mergedPath);
        } catch (\Throwable $exception) {
            $this->cleanupSession($session, true);
            throw $exception;
        }

        return [
            'completed' => true,
            'asset' => $asset,
        ];
    }

    /**
     * 为直传分片生成签名信息。
     *
     * @return array<string, mixed>
     */
    public function signPart(string $sessionId, int $partNumber): array
    {
        $session = $this->getSession($sessionId, [UploadDriver::TRANSPORT_DIRECT_MULTIPART]);
        if ($partNumber < 1 || $partNumber > (int)$session['part_count']) {
            throw new ErrorResponseException('分片编号超出范围');
        }

        $storage = $this->storageManager->driver((string)$session['driver']);
        if (!$storage instanceof MultipartUploadStorageInterface) {
            throw new ErrorResponseException('当前上传通道不支持分片直传');
        }

        $signed = $storage->signMultipartPart(
            $this->objectKey((string)$session['storage_path'], (string)$session['object_name']),
            (string)$session['upload_id'],
            $partNumber
        );

        return array_merge([
            'upload_session_id' => $session['session_id'],
            'part_number' => $partNumber,
        ], $signed);
    }

    /**
     * 完成直传上传并写入文件记录。
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function complete(array $payload): array
    {
        $session = $this->getSession((string)($payload['upload_session_id'] ?? ''), [
            UploadDriver::TRANSPORT_DIRECT_SINGLE,
            UploadDriver::TRANSPORT_DIRECT_MULTIPART,
        ]);

        $completeToken = trim((string)($payload['complete_token'] ?? ''));
        if ($completeToken === '' || $completeToken !== (string)$session['complete_token']) {
            throw new ErrorResponseException('上传完成校验失败');
        }

        $storage = $this->storageManager->driver((string)$session['driver']);
        $objectKey = $this->objectKey((string)$session['storage_path'], (string)$session['object_name']);

        if ($session['transport'] === UploadDriver::TRANSPORT_DIRECT_MULTIPART) {
            if (!$storage instanceof MultipartUploadStorageInterface) {
                throw new ErrorResponseException('当前上传通道不支持分片直传');
            }

            $partsInput = is_array($payload['parts'] ?? null) ? $payload['parts'] : [];
            if ($partsInput === []) {
                throw new ErrorResponseException('缺少分片完成信息');
            }

            $parts = [];
            foreach ($partsInput as $part) {
                $parts[] = [
                    'PartNumber' => (int)($part['part_number'] ?? 0),
                    'ETag' => trim((string)($part['etag'] ?? '')),
                ];
            }
            // 对象存储要求按 PartNumber 顺序完成分片；前端并发上传后返回顺序不可信。
            usort($parts, static fn (array $left, array $right): int => $left['PartNumber'] <=> $right['PartNumber']);

            $storage->completeMultipartUpload($objectKey, (string)$session['upload_id'], $parts);
        }

        $info = $storage->info($objectKey);
        $sizeByte = (int)($info['size_byte'] ?? 0);
        $mimeType = trim((string)($info['mime_type'] ?? 'application/octet-stream'));

        $this->ensureActualMetaAllowed($session, $sizeByte, $mimeType, (string)$session['hash']);
        $asset = $this->createFileRecord($session, $sizeByte, $mimeType, (string)$session['hash']);
        $this->sessions->delete((string)$session['session_id']);

        return $asset;
    }

    /**
     * 中止上传会话并清理相关临时资源。
     *
     * @return array<string, mixed>
     */
    public function abort(string $sessionId): array
    {
        $session = $this->getSession($sessionId, [
            UploadDriver::TRANSPORT_RELAY_CHUNK,
            UploadDriver::TRANSPORT_DIRECT_SINGLE,
            UploadDriver::TRANSPORT_DIRECT_MULTIPART,
        ], false, true);

        $this->cleanupSession($session, true);

        return [
            'upload_session_id' => $session['session_id'],
            'status' => 'aborted',
        ];
    }

    /**
     * 尝试基于 hash 秒传复用已有对象。
     *
     * @param array{tenant_id:int,auth_user_model:string,uid:int} $principal
     * @return null|array<string, mixed>
     */
    private function tryFastUpload(string $driver, string $hash, string $originName, string $category, array $principal): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $hash)) {
            throw new ErrorResponseException('哈希命名模式要求传入合法的 MD5 值');
        }

        /** @var Builder $query */
        $query = SystemFile::withTrashed()
            ->where('driver', $driver)
            ->where('hash', $hash)
            ->orderByDesc('id');
        // 秒传只能复用当前操作者数据范围内的文件；范围外 hash 命中退回正常上传，避免猜 hash 复用不可见对象。
        ScopeProcessor::applyScope($query, $this->currentUploadUser($principal), 'created_by');

        /** @var null|SystemFile $existing */
        $existing = $query->first();

        if (!$existing || !$this->storageManager->driver($driver)->has($this->objectKey((string)$existing->storage_path, (string)$existing->object_name))) {
            return null;
        }

        $tenantId = (int)$principal['tenant_id'];
        if ($tenantId <= TenantContext::UNSET_TENANT_ID) {
            throw new UnauthorizedResponseException('上传租户上下文无效');
        }

        $file = $this->createSystemFileForTenant($tenantId, [
            'tenant_id' => $principal['tenant_id'],
            'scene' => $category,
            'driver' => $driver,
            'url' => $this->config->buildPublicUrl($driver, (string)$existing->storage_path, (string)$existing->object_name),
            'hash' => $hash,
            'suffix' => $existing->suffix,
            'storage_mode' => UploadDriver::storageMode($driver),
            'origin_name' => $originName,
            'object_name' => $existing->object_name,
            'storage_path' => $existing->storage_path,
            'mime_type' => $existing->mime_type,
            'size_byte' => $existing->size_byte,
            'size_info' => $existing->size_info,
            'remark' => '',
            'created_by' => $principal['uid'],
            'updated_by' => $principal['uid'],
        ]);

        return $this->files->formatFileRecord($file->fresh()?->toArray() ?? $file->toArray());
    }

    /**
     * 持久化上传内容并写入文件记录。
     *
     * @param array<string, mixed> $session
     * @return array<string, mixed>
     */
    private function persistUploadedAsset(array $session, string $content, string $mimeType, int $sizeByte, string $hash): array
    {
        $this->ensureActualMetaAllowed($session, $sizeByte, $mimeType, $hash);
        $driver = (string)$session['driver'];
        $objectKey = $this->objectKey((string)$session['storage_path'], (string)$session['object_name']);
        $this->storageManager->driver($driver)->set($objectKey, $content, false, (string)($session['origin_name'] ?? ''), [
            'mime_type' => $mimeType,
        ]);

        $asset = $this->createFileRecord($session, $sizeByte, $mimeType, $hash);
        $this->sessions->delete((string)$session['session_id']);

        return $asset;
    }

    /**
     * 创建系统文件记录并返回标准化结构。
     *
     * @param array<string, mixed> $session
     * @return array<string, mixed>
     */
    private function createFileRecord(array $session, int $sizeByte, string $mimeType, string $hash): array
    {
        $driver = (string)$session['driver'];
        $tenantId = (int)($session['tenant_id'] ?? TenantContext::UNSET_TENANT_ID);
        if ($tenantId <= TenantContext::UNSET_TENANT_ID) {
            throw new UnauthorizedResponseException('上传会话租户无效');
        }

        $operatorId = (int)($session['created_by'] ?? 0);
        if ($operatorId <= 0) {
            throw new UnauthorizedResponseException('上传会话创建人无效');
        }

        $file = $this->createSystemFileForTenant($tenantId, [
            'tenant_id' => $tenantId,
            'scene' => (string)$session['scene'],
            'driver' => $driver,
            'url' => $this->config->buildPublicUrl($driver, (string)$session['storage_path'], (string)$session['object_name']),
            'hash' => $hash !== '' ? $hash : null,
            'suffix' => (string)$session['suffix'],
            'storage_mode' => UploadDriver::storageMode($driver),
            'origin_name' => (string)$session['origin_name'],
            'object_name' => (string)$session['object_name'],
            'storage_path' => (string)$session['storage_path'],
            'mime_type' => $mimeType,
            'size_byte' => $sizeByte,
            'size_info' => $this->formatBytes($sizeByte),
            'remark' => '',
            'created_by' => $operatorId,
            'updated_by' => $operatorId,
        ]);

        return $this->files->formatFileRecord($file->fresh()?->toArray() ?? $file->toArray());
    }

    /**
     * 系统文件是租户业务数据，落库时必须同时恢复租户上下文并显式写入目标租户，避免异步上传完成阶段退回 tenant_id=0。
     *
     * @param array<string, mixed> $payload
     */
    private function createSystemFileForTenant(int $tenantId, array $payload): SystemFile
    {
        if ($tenantId <= TenantContext::UNSET_TENANT_ID) {
            throw new UnauthorizedResponseException('上传租户上下文无效');
        }

        $payload['tenant_id'] = $tenantId;

        return TenantContext::withTenant($tenantId, function () use ($payload): SystemFile {
            return TenantContext::withExplicitTenantWrite(function () use ($payload): SystemFile {
                /* @var SystemFile $file */
                return SystemFile::query()->create($payload);
            });
        });
    }

    /**
     * 读取并校验上传会话合法性。
     *
     * @param array<int, string> $transports
     * @return array<string, mixed>
     */
    private function getSession(string $sessionId, array $transports, bool $requireActiveStatus = true, bool $allowExpired = false): array
    {
        if (trim($sessionId) === '') {
            throw new ErrorResponseException('上传会话不存在');
        }

        $session = $this->sessions->get($sessionId);
        if (!$session) {
            throw new ErrorResponseException('上传会话不存在');
        }

        if (!in_array((string)($session['transport'] ?? ''), $transports, true)) {
            throw new ErrorResponseException('上传会话状态与当前操作不匹配');
        }

        $principal = $this->currentUploadPrincipal();
        // 上传会话服务于 System 后台、Project 前台等多个登录模型；必须同时绑定租户、模型和用户 ID。
        if (
            (int)($session['tenant_id'] ?? 0) !== $principal['tenant_id']
            || (string)($session['auth_user_model'] ?? '') !== $principal['auth_user_model']
            || (int)($session['created_by'] ?? 0) !== $principal['uid']
        ) {
            throw new NotAllowResponseException('无权操作该上传会话');
        }

        if (!$allowExpired && (int)($session['expires_at'] ?? 0) <= time()) {
            $this->sessions->delete($sessionId);
            throw new ErrorResponseException('上传会话已过期，请重新发起上传');
        }

        if ($requireActiveStatus && !in_array((string)($session['status'] ?? ''), ['initialized', 'uploading'], true)) {
            throw new ErrorResponseException('上传会话不可重复使用，请重新发起上传');
        }

        return $session;
    }

    /**
     * 校验预检阶段声明的文件大小是否超限。
     *
     * @param array<string, mixed> $common
     */
    private function ensureExpectedSizeAllowed(int $sizeByte, array $common): void
    {
        $maxSizeBytes = (int)($common['max_size_mb'] ?? 0) * 1024 * 1024;
        if ($maxSizeBytes > 0 && $sizeByte > $maxSizeBytes) {
            throw new ErrorResponseException(sprintf('文件大小不能超过 %d MB', (int)$common['max_size_mb']));
        }
    }

    /**
     * 校验上传完成后的真实元数据。
     *
     * @param array<string, mixed> $session
     */
    private function ensureActualMetaAllowed(array $session, int $sizeByte, string $mimeType, string $hash): void
    {
        if ($sizeByte <= 0) {
            throw new ErrorResponseException('上传文件为空或大小无效');
        }

        $expectedSize = (int)($session['size_byte'] ?? 0);
        if ($expectedSize > 0 && $sizeByte !== $expectedSize) {
            throw new ErrorResponseException('上传文件大小与预检信息不一致');
        }

        $this->ensureExpectedSizeAllowed($sizeByte, $this->config->getCommonConfig());
        $this->ensureMimeTypeAllowed((string)$session['scene'], $mimeType, (string)$session['suffix']);

        $expectedHash = strtolower(trim((string)($session['hash'] ?? '')));
        if ($expectedHash !== '' && $hash !== '' && $expectedHash !== strtolower($hash)) {
            throw new ErrorResponseException('上传文件哈希与预检信息不一致');
        }
    }

    /**
     * 校验 MIME 类型与业务分类/后缀是否匹配。
     */
    private function ensureMimeTypeAllowed(string $category, string $mimeType, ?string $suffix = null): void
    {
        $mimeType = strtolower(trim($mimeType));
        if ($mimeType === '') {
            throw new ErrorResponseException('无法识别上传文件类型');
        }

        $patterns = [];
        if ($suffix !== null && $suffix !== '') {
            $patterns = UploadDriver::extensionMimePatterns()[strtolower($suffix)] ?? [];
        }
        if ($patterns === []) {
            $patterns = match ($category) {
                UploadDriver::CATEGORY_IMAGE => ['image/'],
                UploadDriver::CATEGORY_VIDEO => ['video/'],
                default => ['application/', 'text/', 'audio/'],
            };
        }

        foreach ($patterns as $pattern) {
            $pattern = strtolower(trim($pattern));
            if ($pattern === '') {
                continue;
            }
            if (str_ends_with($pattern, '/')) {
                if (str_starts_with($mimeType, $pattern)) {
                    return;
                }
                continue;
            }
            if ($mimeType === $pattern) {
                return;
            }
        }

        throw new ErrorResponseException('上传文件类型与扩展名不匹配');
    }

    /**
     * 根据驱动能力与文件大小选择上传传输模式。
     *
     * @param array<string, mixed> $common
     */
    private function resolveTransport(string $driver, array $common, int $sizeByte, string $uploadType): string
    {
        $chunkThreshold = (int)($common['chunk_threshold_mb'] ?? 20) * 1024 * 1024;
        $multipartThreshold = (int)($common['multipart_threshold_mb'] ?? 20) * 1024 * 1024;
        $uploadType = strtolower(trim($uploadType));

        if ($driver === UploadDriver::DRIVER_LOCAL) {
            return $sizeByte >= $chunkThreshold ? UploadDriver::TRANSPORT_RELAY_CHUNK : UploadDriver::TRANSPORT_RELAY_SINGLE;
        }

        if ($uploadType === 'relay') {
            return $sizeByte >= $chunkThreshold ? UploadDriver::TRANSPORT_RELAY_CHUNK : UploadDriver::TRANSPORT_RELAY_SINGLE;
        }

        if (UploadDriver::supportsMultipartUpload($driver) && $sizeByte >= $multipartThreshold) {
            return UploadDriver::TRANSPORT_DIRECT_MULTIPART;
        }

        if (UploadDriver::supportsDirectUpload($driver)) {
            return UploadDriver::TRANSPORT_DIRECT_SINGLE;
        }

        return $sizeByte >= $chunkThreshold ? UploadDriver::TRANSPORT_RELAY_CHUNK : UploadDriver::TRANSPORT_RELAY_SINGLE;
    }

    /**
     * 解析分片大小（字节）。
     *
     * @param array<string, mixed> $common
     */
    private function resolvePartSizeBytes(string $transport, array $common): int
    {
        if (in_array($transport, [UploadDriver::TRANSPORT_RELAY_CHUNK, UploadDriver::TRANSPORT_DIRECT_MULTIPART], true)) {
            return max(1, (int)($common['part_size_mb'] ?? 5)) * 1024 * 1024;
        }

        return max(1, (int)($common['part_size_mb'] ?? 5)) * 1024 * 1024;
    }

    /**
     * 生成对象存储路径与对象名。
     *
     * @return array{0:string,1:string}
     */
    private function makeObjectLocation(string $originName, string $suffix, string $hash, string $nameType): array
    {
        if ($nameType === UploadDriver::NAME_TYPE_HASH) {
            if (!preg_match('/^[a-f0-9]{32}$/', $hash)) {
                throw new ErrorResponseException('哈希命名模式要求传入合法的 MD5 值');
            }

            return [
                substr($hash, 0, 2),
                substr($hash, 2) . '.' . $suffix,
            ];
        }

        $storagePath = date('Ymd');
        $unique = substr(hash('sha256', $originName . microtime(true) . random_int(1000, 9999)), 0, 24);

        return [
            $storagePath,
            sprintf('%s_%s.%s', date('His'), $unique, $suffix),
        ];
    }

    /**
     * 拼接对象存储完整键。
     */
    private function objectKey(string $storagePath, string $objectName): string
    {
        return trim(trim($storagePath, '/') . '/' . ltrim($objectName, '/'), '/');
    }

    /**
     * 将字节数转换为可读大小。
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = $bytes / 1024;
        $index = 0;
        while ($value >= 1024 && $index < count($units) - 1) {
            $value /= 1024;
            ++$index;
        }

        return number_format($value, $value >= 10 ? 1 : 2) . ' ' . $units[$index];
    }

    /**
     * 检测文件 MIME 类型。
     */
    private function detectMimeType(string $filePath): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($filePath);
        if (!is_string($mimeType) || trim($mimeType) === '') {
            return 'application/octet-stream';
        }

        return trim($mimeType);
    }

    /**
     * 清理上传会话关联资源（分片、远端对象、会话索引）。
     *
     * @param array<string, mixed> $session
     */
    private function cleanupSession(array $session, bool $deleteRemoteObject = false): void
    {
        $transport = (string)($session['transport'] ?? '');
        $objectKey = $this->objectKey((string)($session['storage_path'] ?? ''), (string)($session['object_name'] ?? ''));
        $storage = $this->storageManager->driver((string)($session['driver'] ?? UploadDriver::DRIVER_LOCAL));

        if ($transport === UploadDriver::TRANSPORT_DIRECT_MULTIPART && $storage instanceof MultipartUploadStorageInterface && trim((string)($session['upload_id'] ?? '')) !== '') {
            $storage->abortMultipartUpload($objectKey, (string)$session['upload_id']);
        }

        if ($deleteRemoteObject && in_array($transport, [UploadDriver::TRANSPORT_DIRECT_SINGLE, UploadDriver::TRANSPORT_DIRECT_MULTIPART], true) && $objectKey !== '') {
            // 只有用户主动中止或回收会话时才删除远端对象；普通完成失败不删除已入库对象，避免误删复用文件。
            $storage->del($objectKey);
        }

        if ($transport === UploadDriver::TRANSPORT_RELAY_CHUNK) {
            $this->clearChunkDir($this->chunkDir((string)$session['session_id']));
            @unlink($this->mergedFilePath((string)$session['session_id']));
        }

        $this->sessions->delete((string)$session['session_id']);
    }

    /**
     * 获取分块临时目录路径。
     */
    private function chunkDir(string $sessionId): string
    {
        return runpath('runtime/cache/upload-chunks/' . $sessionId);
    }

    /**
     * 获取分块合并临时文件路径。
     */
    private function mergedFilePath(string $sessionId): string
    {
        $dir = runpath('runtime/cache/upload-merged');
        is_dir($dir) || mkdir($dir, 0777, true);

        return $dir . '/' . $sessionId . '.bin';
    }

    /**
     * 清理分块临时目录。
     */
    private function clearChunkDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach ((array)glob($dir . '/*') as $file) {
            @unlink((string)$file);
        }
        @rmdir($dir);
    }

    /**
     * 获取当前上传主体。
     *
     * @return array{tenant_id:int,auth_user_model:string,uid:int}
     */
    private function currentUploadPrincipal(): array
    {
        $claims = auth_claims();
        $userId = (int)($claims['uid'] ?? 0);
        $authUserModel = trim((string)($claims['class'] ?? ''));
        if ($userId <= 0) {
            throw new UnauthorizedResponseException('未登录或登录状态已失效');
        }
        if ($authUserModel === '' || !class_exists($authUserModel) || !is_a($authUserModel, UserModelInterface::class, true)) {
            throw new UnauthorizedResponseException('登录用户模型无效');
        }

        // 上传入口同时服务后台用户和插件前台账号；先按 Token 声明的模型恢复登录态，
        // 再读取租户上下文，避免 AOP、日志或复用服务调用时上下文尚未建立导致误判。
        $user = $this->currentUploadUser([
            'tenant_id' => 0,
            'auth_user_model' => $authUserModel,
            'uid' => $userId,
        ]);
        $tenantId = $this->tenantIdFromUser($user);
        if ($tenantId <= 0) {
            throw new UnauthorizedResponseException('租户上下文无效，请重新登录');
        }
        TenantContext::set($tenantId);

        return [
            'tenant_id' => $tenantId,
            'auth_user_model' => $authUserModel,
            'uid' => $userId,
        ];
    }

    /**
     * 按 Token 中的登录模型恢复当前上传用户，避免 Project 前台账号被默认 SystemUser 解析为空。
     *
     * @param array{tenant_id:int,auth_user_model:string,uid:int} $principal
     */
    private function currentUploadUser(array $principal): UserModelInterface
    {
        $user = user($principal['auth_user_model']);
        if (!$user instanceof UserModelInterface || $user->getId() !== $principal['uid']) {
            throw new UnauthorizedResponseException('未登录或登录状态已失效');
        }

        return $user;
    }

    /**
     * 从登录用户原始属性解析租户，避免 toArray() 触发角色、权限等依赖租户上下文的附加查询。
     */
    private function tenantIdFromUser(UserModelInterface $user): int
    {
        return TenantUserResolver::tenantId($user);
    }
}
