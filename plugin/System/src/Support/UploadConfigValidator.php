<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace System\Support;

use Library\Exception\ErrorResponseException;
use System\Support\Storage\CosStorage;
use System\Support\Storage\OssStorage;
use System\Support\Storage\QiniuStorage;

/**
 * 上传配置结构校验。
 */
final class UploadConfigValidator
{
    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function validate(array $config): array
    {
        $activeMode = (string)($config['active_mode'] ?? '');
        if (!in_array($activeMode, UploadDriver::drivers(), true)) {
            throw new ErrorResponseException('当前启用通道配置无效');
        }

        $drivers = is_array($config['drivers'] ?? null) ? $config['drivers'] : [];
        foreach (UploadDriver::drivers() as $driver) {
            $driverConfig = is_array($drivers[$driver] ?? null) ? $drivers[$driver] : [];
            $drivers[$driver] = $this->validateDriver($driver, $driverConfig);
        }

        $common = $this->validateCommon(is_array($config['common'] ?? null) ? $config['common'] : []);

        $activeDriver = $drivers[$activeMode] ?? null;
        if (!$activeDriver || !$this->toBool($activeDriver['enabled'] ?? false)) {
            throw new ErrorResponseException('当前启用通道未启用');
        }

        if ($activeMode !== UploadDriver::DRIVER_LOCAL) {
            foreach (UploadDriver::driverRequiredFields($activeMode) as $field) {
                if (trim((string)($activeDriver[$field] ?? '')) === '') {
                    throw new ErrorResponseException(sprintf('当前启用通道缺少参数 %s', $field));
                }
            }

            foreach (UploadDriver::driverSecretFields($activeMode) as $field) {
                if (trim((string)($activeDriver[UploadDriver::encryptedField($field)] ?? '')) === '') {
                    throw new ErrorResponseException(sprintf('当前启用通道缺少密钥 %s', $field));
                }
            }
        }

        return [
            'active_mode' => $activeMode,
            'common' => $common,
            'drivers' => $drivers,
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function validateCommon(array $config): array
    {
        $config['name_type'] = UploadDriver::normalizeNameType((string)($config['name_type'] ?? UploadDriver::NAME_TYPE_HASH));
        $config['link_type'] = UploadDriver::normalizeLinkType((string)($config['link_type'] ?? UploadDriver::LINK_TYPE_FULL_URL));
        $config['allow_exts'] = UploadDriver::normalizeAllowExts((string)($config['allow_exts'] ?? ''));
        $config['protocol'] = UploadDriver::normalizeProtocol((string)($config['protocol'] ?? UploadDriver::PROTOCOL_HTTPS));
        $config['max_size_mb'] = $this->positiveInt($config['max_size_mb'] ?? 1, '最大上传体积必须大于 0');
        $config['chunk_threshold_mb'] = $this->positiveInt($config['chunk_threshold_mb'] ?? 1, '分块阈值必须大于 0');
        $config['multipart_threshold_mb'] = $this->positiveInt($config['multipart_threshold_mb'] ?? 1, '分片阈值必须大于 0');
        $config['part_size_mb'] = $this->positiveInt($config['part_size_mb'] ?? 1, '分片大小必须大于 0');

        if ($config['allow_exts'] === '') {
            throw new ErrorResponseException('允许上传后缀不能为空');
        }

        if ($config['multipart_threshold_mb'] < $config['part_size_mb']) {
            throw new ErrorResponseException('分片阈值不能小于分片大小');
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function validateDriver(string $driver, array $config): array
    {
        $config['enabled'] = $this->toBool($config['enabled'] ?? false);
        $config['title'] = trim((string)($config['title'] ?? ''));

        if ($driver === UploadDriver::DRIVER_LOCAL) {
            $storagePath = trim((string)($config['storage_path'] ?? 'upload'));
            if ($storagePath === '') {
                $storagePath = 'upload';
            }
            if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $storagePath) || str_starts_with($storagePath, '//')) {
                throw new ErrorResponseException('本地存储路径必须是相对 public 目录的站内路径');
            }

            $config['storage_path'] = UploadDriver::normalizePath($storagePath);
            $config['domain'] = UploadDriver::normalizeDomain((string)($config['domain'] ?? ''));
            if ($config['storage_path'] === '') {
                throw new ErrorResponseException('本地存储路径不能为空');
            }

            return $config;
        }

        foreach (UploadDriver::driverRequiredFields($driver) as $field) {
            $value = trim((string)($config[$field] ?? ''));
            if ($field === 'region') {
                $config[$field] = $this->normalizeRegion($driver, $value);
                continue;
            }

            if ($driver === UploadDriver::DRIVER_OSS && $field === 'endpoint') {
                $config[$field] = UploadDriver::normalizeDomain($value);
                continue;
            }

            if ($driver === UploadDriver::DRIVER_ALIST && $field === 'endpoint') {
                $config[$field] = UploadDriver::normalizeHttpEndpoint($value);
                continue;
            }

            if ($field === 'domain') {
                $config[$field] = UploadDriver::normalizeDomain($value);
                continue;
            }

            if ($driver === UploadDriver::DRIVER_ALIST && $field === 'root') {
                $config[$field] = UploadDriver::normalizeBaseUrl($value);
                continue;
            }

            $config[$field] = $value;
        }

        if ($driver === UploadDriver::DRIVER_ALIST) {
            $config['domain'] = UploadDriver::normalizeDomain((string)($config['domain'] ?? ''));
            $config['public_path'] = UploadDriver::normalizeBaseUrl((string)($config['public_path'] ?? '/d'));
        }

        foreach (UploadDriver::driverSecretFields($driver) as $field) {
            $config[UploadDriver::encryptedField($field)] = trim((string)($config[UploadDriver::encryptedField($field)] ?? ''));
        }

        if (!$config['enabled']) {
            return $config;
        }

        foreach (UploadDriver::driverRequiredFields($driver) as $field) {
            if (trim((string)($config[$field] ?? '')) === '') {
                throw new ErrorResponseException(sprintf('上传通道 %s 缺少必填参数 %s', $driver, $field));
            }
        }

        foreach (UploadDriver::driverSecretFields($driver) as $field) {
            if (trim((string)($config[UploadDriver::encryptedField($field)] ?? '')) === '') {
                throw new ErrorResponseException(sprintf('上传通道 %s 缺少密钥 %s', $driver, $field));
            }
        }

        return $config;
    }

    /**
     * 断言值为正整数。
     */
    private function positiveInt(mixed $value, string $message): int
    {
        $value = (int)$value;
        if ($value <= 0) {
            throw new ErrorResponseException($message);
        }

        return $value;
    }

    /**
     * 将输入归一化为布尔值。
     */
    private function toBool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL) || (int)$value === 1;
    }

    /**
     * 规范化并校验区域值。
     */
    private function normalizeRegion(string $driver, string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return '';
        }

        $regions = $this->driverRegionValues($driver);
        if ($regions === [] || in_array($value, $regions, true)) {
            return $value;
        }

        throw new ErrorResponseException(sprintf('上传通道 %s 的存储区域无效', $driver));
    }

    /**
     * @return array<int, string>
     */
    private function driverRegionValues(string $driver): array
    {
        $regions = match ($driver) {
            UploadDriver::DRIVER_OSS => OssStorage::region(),
            UploadDriver::DRIVER_QINIU => QiniuStorage::region(),
            UploadDriver::DRIVER_COS => CosStorage::region(),
            default => [],
        };

        return array_values(array_filter(array_map(
            static fn (array $item): string => strtolower(trim((string)($item['value'] ?? ''))),
            $regions
        )));
    }
}
