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
use Library\Exception\ErrorResponseException;
use Library\Helper\CoderHelper;
use Library\Helper\RequestHelper;
use Library\Support\ModelChangeLog;
use System\Model\SystemData;
use System\Support\Storage\AlistStorage;
use System\Support\Storage\CosStorage;
use System\Support\Storage\LocalStorage;
use System\Support\Storage\OssStorage;
use System\Support\Storage\QiniuStorage;
use System\Support\UploadConfigValidator;
use System\Support\UploadDriver;

/**
 * 上传通道配置服务。
 *
 * 统一负责默认值合并、敏感字段加密存储和界面脱敏展示。
 */
final class UploadConfigService
{
    /**
     * @param ConfigInterface $configInterface 配置读取接口
     * @param UploadConfigValidator $validator 上传配置校验器
     */
    public function __construct(
        private ConfigInterface $configInterface,
        private UploadConfigValidator $validator,
    ) {}

    /**
     * 获取后台配置页可直接使用的脱敏配置。
     *
     * @return array<string, mixed>
     */
    public function getConfigForDisplay(): array
    {
        $config = $this->getRawConfig();

        foreach (UploadDriver::remoteDrivers() as $driver) {
            $driverConfig = is_array($config['drivers'][$driver] ?? null) ? $config['drivers'][$driver] : [];
            foreach (UploadDriver::driverSecretFields($driver) as $field) {
                $encryptedField = UploadDriver::encryptedField($field);
                $secret = $this->decryptSecret((string)($driverConfig[$encryptedField] ?? ''));
                $config['drivers'][$driver][$field . '_masked'] = UploadDriver::mask($secret);
                $config['drivers'][$driver][$field . '_configured'] = $secret !== '';
                $config['drivers'][$driver][$field] = '';
                unset($config['drivers'][$driver][$encryptedField]);
            }
        }

        $config['driver_meta'] = $this->driverMeta();

        return $config;
    }

    /**
     * 更新上传配置并持久化。
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function updateConfig(array $input): array
    {
        $existing = $this->getRawConfig();
        $merged = $this->mergeConfig(UploadDriver::defaultConfig(), $input);
        $merged = $this->applySensitiveFields($merged, $input, $existing);
        $validated = $this->validator->validate($merged);

        SystemData::query()->updateOrCreate(
            ['name' => 'config_upload'],
            [
                'value' => $validated,
                'remark' => '上传通道配置',
            ]
        );
        $this->recordUploadConfigChange($existing, $validated);

        return $this->getConfigForDisplay();
    }

    /**
     * 获取完整配置（含加密字段）。
     *
     * @return array<string, mixed>
     */
    public function getRawConfig(): array
    {
        $defaults = UploadDriver::defaultConfig();
        /** @var null|SystemData $record */
        $record = SystemData::query()
            ->where('name', 'config_upload')
            ->whereNull('deleted_at')
            ->first();

        if (!$record) {
            SystemData::query()->updateOrCreate(
                ['name' => 'config_upload'],
                [
                    'value' => $defaults,
                    'remark' => '上传通道配置',
                ]
            );

            return $defaults;
        }

        $stored = is_array($record->value ?? null) ? $record->value : [];
        $merged = $this->mergeConfig($defaults, $stored);
        $merged = UploadDriver::normalizeRuntimeConfig($merged);

        return $this->validator->validate($merged);
    }

    /**
     * @return array<string, mixed>
     */
    public function getCommonConfig(): array
    {
        $config = $this->getRawConfig();
        return is_array($config['common'] ?? null) ? $config['common'] : [];
    }

    /**
     * 获取指定驱动配置，可选解密敏感字段。
     *
     * @return array<string, mixed>
     */
    public function getDriverConfig(string $driver, bool $decryptSecrets = false): array
    {
        $config = $this->getRawConfig();
        $driverConfig = is_array($config['drivers'][$driver] ?? null) ? $config['drivers'][$driver] : [];
        if (!$decryptSecrets || $driver === UploadDriver::DRIVER_LOCAL) {
            return $driverConfig;
        }

        foreach (UploadDriver::driverSecretFields($driver) as $field) {
            $driverConfig[$field] = $this->decryptSecret((string)($driverConfig[UploadDriver::encryptedField($field)] ?? ''));
        }

        return $driverConfig;
    }

    /**
     * 获取当前启用的上传驱动。
     */
    public function getActiveUploadMode(): string
    {
        $config = $this->getRawConfig();
        return (string)($config['active_mode'] ?? UploadDriver::DRIVER_LOCAL);
    }

    /**
     * 解析上传驱动并校验可用性。
     */
    public function resolveUploadMode(?string $driver = null): string
    {
        $driver = trim((string)$driver);
        if ($driver === '') {
            return $this->getActiveUploadMode();
        }

        if (!in_array($driver, UploadDriver::drivers(), true)) {
            throw new ErrorResponseException(sprintf('上传通道 %s 不存在', $driver));
        }

        $driverConfig = $this->getDriverConfig($driver);
        if (!(bool)($driverConfig['enabled'] ?? false)) {
            throw new ErrorResponseException(sprintf('上传通道 %s 未启用', $driver));
        }

        return $driver;
    }

    /**
     * 上传运行时只需要可执行规则，不需要敏感字段。
     *
     * @return array<string, mixed>
     */
    public function getRuntimeConfig(): array
    {
        $config = $this->getRawConfig();
        $activeMode = (string)$config['active_mode'];
        $drivers = [];

        foreach (UploadDriver::drivers() as $driver) {
            $driverConfig = is_array($config['drivers'][$driver] ?? null) ? $config['drivers'][$driver] : [];
            $drivers[$driver] = [
                'enabled' => (bool)($driverConfig['enabled'] ?? false),
                'title' => (string)($driverConfig['title'] ?? ''),
                'direct_upload' => UploadDriver::supportsDirectUpload($driver),
                'multipart_upload' => UploadDriver::supportsMultipartUpload($driver),
                'relay_upload' => true,
            ];
        }

        return [
            'active_mode' => $activeMode,
            'common' => $this->getCommonConfig(),
            'drivers' => $drivers,
            'active_driver' => $drivers[$activeMode] ?? [],
        ];
    }

    /**
     * 获取本地存储公开路径前缀。
     */
    public function getLocalPublicPath(): string
    {
        return UploadDriver::normalizeBaseUrl((string)($this->getDriverConfig(UploadDriver::DRIVER_LOCAL)['storage_path'] ?? 'upload'));
    }

    /**
     * 获取本地存储绝对根目录。
     */
    public function getLocalStorageRoot(): string
    {
        $storagePath = UploadDriver::normalizePath((string)($this->getDriverConfig(UploadDriver::DRIVER_LOCAL)['storage_path'] ?? 'upload'));
        return runpath('public/' . trim($storagePath, '/'));
    }

    /**
     * 基于当前配置构建文件公开访问地址。
     */
    public function buildPublicUrl(string $driver, string $storagePath, string $objectName, ?string $linkType = null): string
    {
        return $this->buildPublicUrlFromConfig(
            $driver,
            $this->getCommonConfig(),
            $this->getDriverConfig($driver),
            $storagePath,
            $objectName,
            $linkType
        );
    }

    /**
     * @param array<string, mixed> $common
     * @param array<string, mixed> $driverConfig
     */
    public function buildPublicUrlFromConfig(
        string $driver,
        array $common,
        array $driverConfig,
        string $storagePath,
        string $objectName,
        ?string $linkType = null
    ): string {
        $linkType ??= UploadDriver::normalizeLinkType((string)($common['link_type'] ?? UploadDriver::LINK_TYPE_FULL_URL));
        $protocol = UploadDriver::normalizeProtocol((string)($common['protocol'] ?? UploadDriver::PROTOCOL_HTTPS));
        $relative = trim(trim($storagePath, '/') . '/' . ltrim($objectName, '/'), '/');

        if ($linkType === UploadDriver::LINK_TYPE_STORAGE_PATH) {
            if ($driver === UploadDriver::DRIVER_ALIST) {
                $root = trim((string)($driverConfig['root'] ?? '/'), '/');
                return trim(($root !== '' ? $root . '/' : '') . $relative, '/');
            }

            return $relative;
        }

        if ($driver === UploadDriver::DRIVER_LOCAL) {
            $localRelative = trim(trim((string)($driverConfig['storage_path'] ?? 'upload'), '/') . '/' . $relative, '/');
            if ($linkType === UploadDriver::LINK_TYPE_RELATIVE_URL) {
                return '/' . $localRelative;
            }

            $domain = UploadDriver::normalizeDomain((string)($driverConfig['domain'] ?? ''));
            if ($domain !== '') {
                return $this->buildAbsoluteUrlFromDomain($domain, $protocol, $localRelative);
            }

            $origin = RequestHelper::getOrigin();
            if ($origin === null) {
                throw new ErrorResponseException('本地存储完整链接缺少访问域名配置，且当前请求上下文不可用');
            }

            return rtrim($origin, '/') . '/' . $localRelative;
        }

        if ($driver === UploadDriver::DRIVER_ALIST) {
            $root = trim((string)($driverConfig['root'] ?? '/'), '/');
            $publicPath = trim((string)($driverConfig['public_path'] ?? '/d'), '/');
            $actualPath = trim(($root !== '' ? $root . '/' : '') . $relative, '/');
            $publicRelative = trim(($publicPath !== '' ? $publicPath . '/' : '') . $actualPath, '/');

            if ($linkType === UploadDriver::LINK_TYPE_RELATIVE_URL) {
                return '/' . $publicRelative;
            }

            $domain = UploadDriver::normalizeDomain((string)($driverConfig['domain'] ?? ''));
            if ($domain !== '') {
                return $this->buildAbsoluteUrlFromDomain($domain, $protocol, $publicRelative);
            }

            $origin = $this->resolveEndpointOrigin((string)($driverConfig['endpoint'] ?? ''));
            if ($origin === null) {
                throw new ErrorResponseException('AList 完整链接缺少访问域名或服务地址配置');
            }

            return rtrim($origin, '/') . '/' . $publicRelative;
        }

        if ($linkType === UploadDriver::LINK_TYPE_RELATIVE_URL) {
            return '/' . $relative;
        }

        $domain = UploadDriver::normalizeDomain((string)($driverConfig['domain'] ?? ''));
        if ($domain === '') {
            throw new ErrorResponseException(sprintf('上传通道 %s 缺少访问域名配置，无法生成完整链接', $driver));
        }

        return $this->buildAbsoluteUrlFromDomain($domain, $protocol, $relative);
    }

    /**
     * 使用域名和协议拼接绝对 URL。
     */
    private function buildAbsoluteUrlFromDomain(string $domain, string $protocol, string $relative): string
    {
        $scheme = $protocol === UploadDriver::PROTOCOL_HTTP ? 'http://' : 'https://';
        if ($protocol === UploadDriver::PROTOCOL_AUTO) {
            $scheme = '//';
        }

        return $scheme . UploadDriver::normalizeDomain($domain) . '/' . trim($relative, '/');
    }

    /**
     * 从 endpoint 解析 origin。
     */
    private function resolveEndpointOrigin(string $endpoint): ?string
    {
        $endpoint = UploadDriver::normalizeHttpEndpoint($endpoint);
        if ($endpoint === '') {
            return null;
        }

        $parsed = parse_url($endpoint);
        if (!is_array($parsed) || empty($parsed['host'])) {
            return null;
        }

        $origin = sprintf('%s://%s', $parsed['scheme'] ?? 'http', $parsed['host']);
        if (isset($parsed['port'])) {
            $origin .= ':' . $parsed['port'];
        }

        return $origin;
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $append
     * @return array<string, mixed>
     */
    private function mergeConfig(array $base, array $append): array
    {
        foreach ($append as $key => $value) {
            if (is_array($value) && is_array($base[$key] ?? null)) {
                $base[$key] = $this->mergeConfig($base[$key], $value);
                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }

    /**
     * @param array<string, mixed> $merged
     * @param array<string, mixed> $input
     * @param array<string, mixed> $existing
     * @return array<string, mixed>
     */
    private function applySensitiveFields(array $merged, array $input, array $existing): array
    {
        foreach (UploadDriver::remoteDrivers() as $driver) {
            $existingDriver = is_array($existing['drivers'][$driver] ?? null) ? $existing['drivers'][$driver] : [];
            $inputDriver = is_array($input['drivers'][$driver] ?? null) ? $input['drivers'][$driver] : [];

            foreach (UploadDriver::driverSecretFields($driver) as $field) {
                $encryptedField = UploadDriver::encryptedField($field);
                $clearField = 'clear_' . $field;

                $merged['drivers'][$driver][$encryptedField] = (string)($existingDriver[$encryptedField] ?? '');
                if (($inputDriver[$clearField] ?? false) === true) {
                    $merged['drivers'][$driver][$encryptedField] = '';
                } elseif (trim((string)($inputDriver[$field] ?? '')) !== '') {
                    $merged['drivers'][$driver][$encryptedField] = $this->encryptSecret((string)$inputDriver[$field]);
                }

                unset(
                    $merged['drivers'][$driver][$field],
                    $merged['drivers'][$driver][$field . '_masked'],
                    $merged['drivers'][$driver][$field . '_configured'],
                    $merged['drivers'][$driver][$clearField],
                );
            }
        }

        return $merged;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function driverMeta(): array
    {
        return [
            UploadDriver::DRIVER_LOCAL => [
                'title' => '本地存储',
                'direct_upload' => UploadDriver::supportsDirectUpload(UploadDriver::DRIVER_LOCAL),
                'multipart_upload' => UploadDriver::supportsMultipartUpload(UploadDriver::DRIVER_LOCAL),
                'regions' => LocalStorage::region(),
                'fields' => [
                    'title' => [
                        'placeholder' => '本地存储',
                        'help' => '用于在上传入口和配置页中显示的通道名称。',
                    ],
                    'storage_path' => [
                        'placeholder' => 'upload',
                        'help' => '相对 public 目录，例如 upload 或 static/uploads。',
                    ],
                    'domain' => [
                        'placeholder' => 'files.example.com',
                        'help' => '可选。完整链接模式下优先使用该域名生成访问地址。',
                    ],
                ],
            ],
            UploadDriver::DRIVER_ALIST => [
                'title' => 'AList',
                'direct_upload' => UploadDriver::supportsDirectUpload(UploadDriver::DRIVER_ALIST),
                'multipart_upload' => UploadDriver::supportsMultipartUpload(UploadDriver::DRIVER_ALIST),
                'regions' => AlistStorage::region(),
                'fields' => [
                    'title' => [
                        'placeholder' => 'AList',
                        'help' => '用于在上传入口和配置页中显示的通道名称。',
                    ],
                    'endpoint' => [
                        'placeholder' => 'http://127.0.0.1:5244',
                        'help' => 'AList 服务端地址，建议填写可从后台访问的完整地址。',
                    ],
                    'root' => [
                        'placeholder' => '/upload',
                        'help' => '文件上传到 AList 的实际根目录，支持多级目录。',
                    ],
                    'public_path' => [
                        'placeholder' => '/d',
                        'help' => 'AList 对外公开访问的路径前缀，例如 /d。',
                    ],
                    'domain' => [
                        'placeholder' => 'storage.example.com',
                        'help' => '可选。完整链接模式下优先使用该域名生成访问地址。',
                    ],
                    'username' => [
                        'placeholder' => 'admin',
                        'help' => '用于调用 AList 接口的登录用户名。',
                    ],
                    'password' => [
                        'placeholder' => '留空表示保留原值',
                        'help' => '用于调用 AList 接口的登录密码。',
                    ],
                ],
            ],
            UploadDriver::DRIVER_OSS => [
                'title' => '阿里云 OSS',
                'direct_upload' => UploadDriver::supportsDirectUpload(UploadDriver::DRIVER_OSS),
                'multipart_upload' => UploadDriver::supportsMultipartUpload(UploadDriver::DRIVER_OSS),
                'regions' => OssStorage::region(),
                'fields' => [
                    'title' => [
                        'placeholder' => '阿里云 OSS',
                        'help' => '用于在上传入口和配置页中显示的通道名称。',
                    ],
                    'region' => [
                        'placeholder' => '请选择存储区域',
                        'help' => '存储区域统一显示为 中文名称(英文值)。',
                    ],
                    'endpoint' => [
                        'placeholder' => 'oss-cn-hangzhou.aliyuncs.com',
                        'help' => '默认根据所选存储区域带出，可按高级场景覆盖。',
                    ],
                    'bucket' => [
                        'placeholder' => 'example-bucket',
                        'help' => '阿里云 OSS Bucket 名称。',
                    ],
                    'domain' => [
                        'placeholder' => 'cdn.example.com',
                        'help' => '完整链接模式下使用的 OSS 自定义域名或回源域名。',
                    ],
                    'access_id' => [
                        'placeholder' => '留空表示保留原值',
                        'help' => '阿里云访问密钥 ID。',
                    ],
                    'access_secret' => [
                        'placeholder' => '留空表示保留原值',
                        'help' => '阿里云访问密钥 Secret。',
                    ],
                ],
            ],
            UploadDriver::DRIVER_QINIU => [
                'title' => '七牛云',
                'direct_upload' => UploadDriver::supportsDirectUpload(UploadDriver::DRIVER_QINIU),
                'multipart_upload' => UploadDriver::supportsMultipartUpload(UploadDriver::DRIVER_QINIU),
                'regions' => QiniuStorage::region(),
                'fields' => [
                    'title' => [
                        'placeholder' => '七牛云',
                        'help' => '用于在上传入口和配置页中显示的通道名称。',
                    ],
                    'region' => [
                        'placeholder' => '请选择存储区域',
                        'help' => '存储区域统一显示为 中文名称(英文值)。',
                    ],
                    'bucket' => [
                        'placeholder' => 'example-bucket',
                        'help' => '七牛云空间名称。',
                    ],
                    'domain' => [
                        'placeholder' => 'static.example.com',
                        'help' => '完整链接模式下使用的七牛云访问域名。',
                    ],
                    'access_key' => [
                        'placeholder' => '留空表示保留原值',
                        'help' => '七牛云 AccessKey。',
                    ],
                    'secret_key' => [
                        'placeholder' => '留空表示保留原值',
                        'help' => '七牛云 SecretKey。',
                    ],
                ],
            ],
            UploadDriver::DRIVER_COS => [
                'title' => '腾讯云 COS',
                'direct_upload' => UploadDriver::supportsDirectUpload(UploadDriver::DRIVER_COS),
                'multipart_upload' => UploadDriver::supportsMultipartUpload(UploadDriver::DRIVER_COS),
                'regions' => CosStorage::region(),
                'fields' => [
                    'title' => [
                        'placeholder' => '腾讯云 COS',
                        'help' => '用于在上传入口和配置页中显示的通道名称。',
                    ],
                    'region' => [
                        'placeholder' => '请选择存储区域',
                        'help' => '存储区域统一显示为 中文名称(英文值)。',
                    ],
                    'bucket' => [
                        'placeholder' => 'examplebucket-1250000000',
                        'help' => '腾讯云 COS Bucket 名称。',
                    ],
                    'domain' => [
                        'placeholder' => 'static.example.com',
                        'help' => '完整链接模式下使用的腾讯云访问域名。',
                    ],
                    'secret_id' => [
                        'placeholder' => '留空表示保留原值',
                        'help' => '腾讯云 SecretId。',
                    ],
                    'secret_key' => [
                        'placeholder' => '留空表示保留原值',
                        'help' => '腾讯云 SecretKey。',
                    ],
                ],
            ],
        ];
    }

    /**
     * 加密上传密钥字段。
     */
    private function encryptSecret(string $value): string
    {
        return CoderHelper::encrypt(trim($value), $this->cipherKey());
    }

    /**
     * 解密上传密钥字段。
     */
    private function decryptSecret(string $value): string
    {
        if (trim($value) === '') {
            return '';
        }

        return (string)CoderHelper::decrypt($value, $this->cipherKey());
    }

    /**
     * 获取配置加解密密钥。
     */
    private function cipherKey(): string
    {
        return (string)$this->configInterface->get('jwt.secret', '');
    }

    /**
     * 上传通道配置包含密钥密文，变更日志只保存脱敏后的配置摘要。
     *
     * @param array<string, mixed> $oldValue
     * @param array<string, mixed> $newValue
     */
    private function recordUploadConfigChange(array $oldValue, array $newValue): void
    {
        /** @var null|SystemData $record */
        $record = SystemData::query()
            ->where('name', 'config_upload')
            ->whereNull('deleted_at')
            ->first();
        if (!$record) {
            return;
        }

        ModelChangeLog::recordFields($record, 'updated', [[
            'field' => 'value',
            'label' => '上传通道配置',
            'old' => $this->sanitizeConfigForLog($oldValue),
            'new' => $this->sanitizeConfigForLog($newValue),
        ]]);
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function sanitizeConfigForLog(array $config): array
    {
        $drivers = is_array($config['drivers'] ?? null) ? $config['drivers'] : [];
        foreach (UploadDriver::remoteDrivers() as $driver) {
            if (!is_array($drivers[$driver] ?? null)) {
                continue;
            }

            foreach (UploadDriver::driverSecretFields($driver) as $field) {
                $encryptedField = UploadDriver::encryptedField($field);
                $drivers[$driver][$field . '_configured'] = trim((string)($drivers[$driver][$encryptedField] ?? '')) !== '';
                unset($drivers[$driver][$field], $drivers[$driver][$encryptedField]);
            }
        }
        $config['drivers'] = $drivers;

        return $config;
    }
}
