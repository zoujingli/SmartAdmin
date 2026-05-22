<?php

declare(strict_types=1);

namespace System\Service;

use Library\Exception\ErrorResponseException;
use System\Contract\StorageInterface;
use System\Support\Storage\AlistStorage;
use System\Support\Storage\CosStorage;
use System\Support\Storage\LocalStorage;
use System\Support\Storage\OssStorage;
use System\Support\Storage\QiniuStorage;
use System\Support\UploadDriver;

final class StorageManager
{
    /**
     * @param UploadConfigService $config 上传配置服务
     */
    public function __construct(
        protected UploadConfigService $config,
    ) {}

    /**
     * 获取指定驱动的存储实例。
     */
    public function driver(?string $driver = null): StorageInterface
    {
        $driver ??= $this->config->getActiveUploadMode();
        return $this->makeDriver($driver, $this->config->getCommonConfig());
    }

    /**
     * 生成下载链接（下载场景强制使用完整 URL 输出）。
     */
    public function downloadUrl(string $driver, string $name, ?string $attname = null): string
    {
        $common = $this->config->getCommonConfig();
        if (($common['link_type'] ?? null) === UploadDriver::LINK_TYPE_STORAGE_PATH) {
            $common['link_type'] = UploadDriver::LINK_TYPE_FULL_URL;
        }

        return $this->makeDriver($driver, $common)->url($name, false, $attname);
    }

    /**
     * @param array<string, mixed> $common
     */
    private function makeDriver(string $driver, array $common): StorageInterface
    {
        return match ($driver) {
            UploadDriver::DRIVER_LOCAL => new LocalStorage(array_merge(
                $this->config->getDriverConfig($driver),
                ['root' => $this->config->getLocalStorageRoot()]
            ), $common),
            UploadDriver::DRIVER_OSS => new OssStorage($this->config->getDriverConfig($driver, true), $common),
            UploadDriver::DRIVER_QINIU => new QiniuStorage($this->config->getDriverConfig($driver, true), $common),
            UploadDriver::DRIVER_COS => new CosStorage($this->config->getDriverConfig($driver, true), $common),
            UploadDriver::DRIVER_ALIST => new AlistStorage($this->config->getDriverConfig($driver, true), $common),
            default => throw new ErrorResponseException(sprintf('不支持的上传通道 %s', $driver)),
        };
    }
}
