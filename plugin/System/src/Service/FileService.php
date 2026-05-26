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

use Hyperf\Contract\ConfigInterface;
use Hyperf\Database\Model\Collection;
use Library\Constants\DataField;
use Library\CoreService;
use Library\Exception\ErrorResponseException;
use Library\Helper\RequestHelper;
use System\Mapper\FileMapper;
use System\Model\SystemFile;
use System\Support\UploadDriver;

final class FileService extends CoreService
{
    private const SIGNED_DOWNLOAD_TTL = 1800;

    /**
     * @param FileMapper $mapper 文件数据访问层
     * @param UploadConfigService $uploadConfig 上传配置服务
     * @param StorageManager $storageManager 存储驱动管理器
     * @param ConfigInterface $config 配置读取接口
     */
    public function __construct(
        protected FileMapper $mapper,
        protected UploadConfigService $uploadConfig,
        protected StorageManager $storageManager,
        protected ConfigInterface $config,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getFileDetail(int $id): array
    {
        $file = $this->findFileOrFail($id);
        /** @var SystemFile $file */
        $deletedAt = $file->deleted_at;

        return $this->formatFileRecord($file->toArray() + [
            'deleted_at' => $deletedAt instanceof \DateTimeInterface ? $deletedAt->format('Y-m-d H:i:s') : null,
        ]);
    }

    /**
     * @return array{type:'local',path:string,name:string}|array{type:'redirect',url:string,name:string}
     */
    public function getDownloadTarget(int $id, ?string $attname = null): array
    {
        $file = $this->findFileOrFail($id);
        /* @var SystemFile $file */

        return $this->buildDownloadTarget($file, $attname);
    }

    /**
     * 生成已由上层业务对象授权过的下载目标。
     *
     * Project 富文本附件下载先校验“当前用户能读对象 + 对象正文确实引用文件”，
     * 因此这里仅按文件 ID 和对象租户读取未删除文件，避免再使用 System 文件管理的数据范围误伤 Project 前台账号，
     * 同时保留 tenant_id 校验防止跨租户猜 ID 下载。
     *
     * @return array{type:'local',path:string,name:string}|array{type:'redirect',url:string,name:string}
     */
    public function getAuthorizedDownloadTarget(int $id, ?string $attname = null, ?int $tenantId = null): array
    {
        $file = $this->findSignedFileOrFail($id);
        if ($tenantId !== null && (int)$file->tenant_id !== $tenantId) {
            throw new ErrorResponseException('文件不存在或无权限下载');
        }

        return $this->buildDownloadTarget($file, $attname);
    }

    /**
     * 校验短期签名后生成下载目标。
     *
     * 签名链接本身是短期访问凭证，不能依赖登录态和数据范围上下文；校验通过后按文件 ID 直接读取未删除文件。
     *
     * @param array<string, mixed> $query
     * @return array{type:'local',path:string,name:string}|array{type:'redirect',url:string,name:string}
     */
    public function getSignedDownloadTarget(int $id, array $query): array
    {
        $expires = (int)$this->scalarQueryValue($query['expires'] ?? null);
        $attname = $this->normalizeDownloadName((string)$this->scalarQueryValue($query['attname'] ?? ''));
        $signature = (string)$this->scalarQueryValue($query['signature'] ?? '');

        if ($expires <= 0 || $expires < time()) {
            throw new ErrorResponseException('下载链接已过期');
        }

        if ($signature === '' || !hash_equals($this->signDownloadUrl($id, $expires, $attname), $signature)) {
            throw new ErrorResponseException('下载链接无效');
        }

        return $this->buildDownloadTarget($this->findSignedFileOrFail($id), $attname);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateFile(int $id, array $data): array
    {
        $file = $this->findFileOrFail($id, true);

        foreach (['origin_name', 'remark'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        $payload = _vali([
            'origin_name.max:255' => '文件名最多 255 位',
            'remark.max:255' => '备注最多 255 位',
        ], $data);

        if ($payload !== []) {
            $payload['updated_by'] = (int)(auth_claims()['uid'] ?? 0);
            $file->update($payload);
        }

        return $this->getFileDetail($id);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function getRecycleList(array $params = []): array
    {
        $params['recycle'] = true;
        return $this->normalizeList($this->mapper->getPageList($params));
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function getFileList(array $params = []): array
    {
        return $this->normalizeList($this->mapper->getPageList($params));
    }

    /**
     * @return array<string, mixed>
     */
    public function dedupeByHash(string $hash, ?string $driver = null): array
    {
        $hash = strtolower(trim($hash));
        if ($hash === '') {
            throw new ErrorResponseException('文件哈希不能为空');
        }

        $query = SystemFile::withTrashed()->where('hash', $hash);
        if ($driver !== null && trim($driver) !== '') {
            $query->where('driver', trim($driver));
        }

        /** @var Collection<int, SystemFile> $records */
        $records = $query->orderByDesc('id')->get();
        $accessibleIds = array_fill_keys(array_map(
            static fn (SystemFile $file): int => (int)$file->id,
            $this->mapper->getOperationModels($records->pluck('id')->map(static fn (mixed $id): int => (int)$id)->all(), true)
        ), true);
        $records = $records->filter(static fn (SystemFile $file): bool => isset($accessibleIds[(int)$file->id]))->values();
        if ($records->count() <= 1) {
            return [
                'hash' => $hash,
                'kept_id' => $records->first()?->id,
                'deleted_ids' => [],
                'deleted_count' => 0,
            ];
        }

        $keeper = $records->first(fn (SystemFile $file): bool => $this->physicalObjectExists($file));
        $keeper ??= $records->first();
        if (!$keeper) {
            throw new ErrorResponseException('去重目标不存在');
        }

        $deleteIds = $records
            ->filter(fn (SystemFile $file): bool => (int)$file->id !== (int)$keeper->id)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int)$id)
            ->values()
            ->all();

        if ($deleteIds !== []) {
            $this->delreal($deleteIds);
        }

        return [
            'hash' => $hash,
            'driver' => $this->resolveDriver($keeper),
            'kept_id' => (int)$keeper->id,
            'deleted_ids' => $deleteIds,
            'deleted_count' => count($deleteIds),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, mixed>
     */
    public function dedupeBatch(array $items): array
    {
        $targets = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $hash = strtolower(trim((string)($item['hash'] ?? '')));
            if ($hash === '') {
                continue;
            }

            $driver = trim((string)($item['driver'] ?? ''));
            $scopeKey = $driver . '|' . $hash;
            $targets[$scopeKey] = [
                'hash' => $hash,
                'driver' => $driver !== '' ? $driver : null,
            ];
        }

        if ($targets === []) {
            throw new ErrorResponseException('没有可去重的文件');
        }

        $groups = [];
        $deletedIds = [];
        $deletedCount = 0;
        foreach ($targets as $target) {
            $result = $this->dedupeByHash($target['hash'], $target['driver']);
            $groups[] = $result;
            $deletedCount += (int)($result['deleted_count'] ?? 0);
            $deletedIds = array_merge($deletedIds, array_map('intval', $result['deleted_ids'] ?? []));
        }

        return [
            'group_count' => count($groups),
            'deleted_count' => $deletedCount,
            'deleted_ids' => array_values(array_unique($deletedIds)),
            'groups' => $groups,
        ];
    }

    /**
     * @param array<int, int|string> $ids
     */
    public function delreal(array $ids): bool
    {
        $idArray = array_values(array_unique(array_map('intval', $ids)));
        if ($idArray === []) {
            return true;
        }

        $files = $this->mapper->getOperationModels($idArray, true);
        if (count($files) !== count($idArray)) {
            return false;
        }

        foreach ($files as $file) {
            if ($this->hasOtherReferences($file)) {
                continue;
            }

            $this->deleteStoredObject($file);
        }

        return $this->mapper->delreal($idArray);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function formatFileRecord(array $row): array
    {
        $driver = $this->resolveDriver($row);
        $url = $this->uploadConfig->buildPublicUrl(
            $driver,
            (string)($row['storage_path'] ?? ''),
            (string)($row['object_name'] ?? '')
        );
        $downloadUrl = $this->buildDownloadUrl(
            (int)($row['id'] ?? 0),
            (string)($row['origin_name'] ?? '')
        );

        return array_merge($row, [
            'driver' => $driver,
            'url' => $url,
            'preview_url' => $url,
            'download_url' => $downloadUrl !== '' ? $downloadUrl : $url,
        ]);
    }

    /**
     * 文件记录写入白名单与字段格式校验；上传流程直接落库时仍保留专用上传服务的物理文件校验。
     */
    protected function filterData(array &$data, array $exists = []): array
    {
        foreach (['scene', 'driver', 'url', 'hash', 'suffix', 'origin_name', 'object_name', 'storage_path', 'mime_type', 'size_info', 'remark'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        $data = _vali([
            'tenant_id',
            'scene',
            'driver',
            'url',
            'hash',
            'suffix',
            'storage_mode',
            'origin_name',
            'object_name',
            'storage_path',
            'mime_type',
            'size_byte',
            'size_info',
            'remark',
            'created_by',
            'updated_by',
            'tenant_id.integer' => '租户 ID 必须为数字',
            'tenant_id.min:0' => '租户 ID 不能小于 0',
            'scene.max:20' => '上传场景最多 20 位',
            'driver.max:20' => '上传通道最多 20 位',
            'url.max:255' => '文件地址最多 255 位',
            'hash.max:64' => '文件哈希最多 64 位',
            'suffix.max:10' => '文件后缀最多 10 位',
            'storage_mode.integer' => '存储方式必须为数字',
            'storage_mode.in:1,2,3,4,5' => '存储方式错误',
            'origin_name.max:255' => '文件名最多 255 位',
            'object_name.max:50' => '存储文件名最多 50 位',
            'storage_path.max:100' => '存储路径最多 100 位',
            'mime_type.max:255' => 'MIME 类型最多 255 位',
            'size_byte.integer' => '文件大小必须为数字',
            'size_byte.min:0' => '文件大小不能小于 0',
            'size_info.max:50' => '文件大小描述最多 50 位',
            'remark.max:255' => '备注最多 255 位',
            'created_by.integer' => '创建者必须为数字',
            'created_by.min:0' => '创建者不能小于 0',
            'updated_by.integer' => '更新者必须为数字',
            'updated_by.min:0' => '更新者不能小于 0',
        ], $data);

        foreach (['tenant_id', 'storage_mode', 'size_byte', 'created_by', 'updated_by'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = (int)$data[$field];
            }
        }

        return $data;
    }

    /**
     * @return array{type:'local',path:string,name:string}|array{type:'redirect',url:string,name:string}
     */
    private function buildDownloadTarget(SystemFile $file, ?string $attname = null): array
    {
        $driver = $this->resolveDriver($file);
        $key = $this->objectKey((string)$file->storage_path, (string)$file->object_name);
        if ($key === '') {
            throw new ErrorResponseException('文件不存在');
        }

        $downloadName = $this->normalizeDownloadName(
            $attname,
            (string)$file->origin_name,
            (string)$file->object_name
        );

        if ($driver === UploadDriver::DRIVER_LOCAL) {
            $path = rtrim($this->uploadConfig->getLocalStorageRoot(), '/') . '/' . $key;
            if (!is_file($path)) {
                throw new ErrorResponseException('文件不存在');
            }

            return [
                'type' => 'local',
                'path' => $path,
                'name' => $downloadName,
            ];
        }

        return [
            'type' => 'redirect',
            'url' => $this->storageManager->downloadUrl($driver, $key, $downloadName),
            'name' => $downloadName,
        ];
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function normalizeList(array $result): array
    {
        $result['items'] = array_map(fn (array $row): array => $this->formatFileRecord($row), $result['items'] ?? []);
        return $result;
    }

    /**
     * 删除文件对应的物理对象。
     */
    private function deleteStoredObject(SystemFile $file): void
    {
        $driver = $this->resolveDriver($file);
        $key = $this->objectKey((string)$file->storage_path, (string)$file->object_name);
        if ($key === '') {
            return;
        }

        $this->storageManager->driver($driver)->del($key);
    }

    /**
     * 检查文件物理对象是否存在。
     */
    private function physicalObjectExists(SystemFile $file): bool
    {
        $driver = $this->resolveDriver($file);
        $key = $this->objectKey((string)$file->storage_path, (string)$file->object_name);
        if ($key === '') {
            return false;
        }

        return $this->storageManager->driver($driver)->has($key);
    }

    /**
     * 判断同一物理对象是否仍被其他记录引用。
     */
    private function hasOtherReferences(SystemFile $file): bool
    {
        return SystemFile::withTrashed()
            ->where('driver', $this->resolveDriver($file))
            ->where('storage_path', (string)$file->storage_path)
            ->where('object_name', (string)$file->object_name)
            ->where('id', '<>', (int)$file->id)
            ->exists();
    }

    /**
     * @param array<string, mixed>|SystemFile $row
     */
    private function resolveDriver(array|SystemFile $row): string
    {
        $driver = trim((string)(is_array($row) ? ($row['driver'] ?? '') : ($row->driver ?? '')));
        if ($driver !== '') {
            return $driver;
        }

        $storageMode = (int)(is_array($row) ? ($row['storage_mode'] ?? 0) : $row->storage_mode);
        return match ($storageMode) {
            SystemFile::STORAGE_MODE_OSS => UploadDriver::DRIVER_OSS,
            SystemFile::STORAGE_MODE_QINIU => UploadDriver::DRIVER_QINIU,
            SystemFile::STORAGE_MODE_COS => UploadDriver::DRIVER_COS,
            SystemFile::STORAGE_MODE_ALIST => UploadDriver::DRIVER_ALIST,
            default => UploadDriver::DRIVER_LOCAL,
        };
    }

    /**
     * 拼接文件对象键。
     */
    private function objectKey(string $storagePath, string $objectName): string
    {
        return trim(trim($storagePath, '/') . '/' . ltrim($objectName, '/'), '/');
    }

    /**
     * 查找文件，不存在时抛异常。
     */
    private function findFileOrFail(int $id, bool $withTrashed = false): SystemFile
    {
        /** @var null|SystemFile $file */
        $file = $withTrashed ? $this->mapper->readWithTrashed($id) : $this->mapper->read($id);
        if (!$file) {
            throw new ErrorResponseException('文件不存在');
        }

        return $file;
    }

    /**
     * 签名下载已完成权限校验，读取文件时需要绕过登录态数据范围和租户上下文。
     */
    private function findSignedFileOrFail(int $id): SystemFile
    {
        /** @var null|SystemFile $file */
        $file = SystemFile::query()
            ->withoutGlobalScope(DataField::TENANT)
            ->whereKey($id)
            ->first();
        if (!$file) {
            throw new ErrorResponseException('文件不存在');
        }

        return $file;
    }

    /**
     * 生成短期签名下载完整地址。
     */
    private function buildDownloadUrl(int $id, string $originName): string
    {
        if ($id <= 0) {
            return '';
        }

        $downloadName = $this->normalizeDownloadName($originName);
        $expires = time() + self::SIGNED_DOWNLOAD_TTL;

        return RequestHelper::url('/system/file/download-signed/' . $id, [
            'expires' => $expires,
            'attname' => $downloadName,
            'signature' => $this->signDownloadUrl($id, $expires, $downloadName),
        ]);
    }

    /**
     * 签名绑定文件 ID、过期时间和下载文件名，防止复制链接后篡改目标或文件名。
     */
    private function signDownloadUrl(int $id, int $expires, string $attname): string
    {
        $secret = trim((string)$this->config->get('jwt.secret', ''));
        if ($secret === '') {
            throw new ErrorResponseException('下载签名密钥未配置');
        }

        $payload = implode("\n", ['system.file.download', (string)$id, (string)$expires, $attname]);
        $signature = hash_hmac('sha256', $payload, $secret, true);

        return rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }

    private function scalarQueryValue(mixed $value): string
    {
        return is_scalar($value) ? trim((string)$value) : '';
    }

    /**
     * 规范化下载文件名，清理非法字符并回退默认值。
     */
    private function normalizeDownloadName(string ...$candidates): string
    {
        foreach ($candidates as $candidate) {
            $name = trim(str_replace(["\0", "\r", "\n"], '', $candidate));
            $name = basename(str_replace('\\', '/', $name));
            if ($name !== '') {
                return $name;
            }
        }

        return 'download';
    }
}
