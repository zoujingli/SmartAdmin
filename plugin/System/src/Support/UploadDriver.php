<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://github.com/zoujingli/SmartAdmin/blob/master/readme.md
 */

namespace System\Support;

use System\Model\SystemFile;

/**
 * 上传通道、运行时能力与默认规则常量。
 */
final class UploadDriver
{
    public const DRIVER_LOCAL = 'local';

    public const DRIVER_OSS = 'oss';

    public const DRIVER_QINIU = 'qiniu';

    public const DRIVER_COS = 'cos';

    public const DRIVER_ALIST = 'alist';

    public const CATEGORY_IMAGE = 'image';

    public const CATEGORY_FILE = 'file';

    public const CATEGORY_VIDEO = 'video';

    public const NAME_TYPE_HASH = 'hash';

    public const NAME_TYPE_DATE_RANDOM = 'date-random';

    public const LINK_TYPE_RELATIVE_URL = 'relative_url';

    public const LINK_TYPE_FULL_URL = 'full_url';

    public const LINK_TYPE_STORAGE_PATH = 'storage_path';

    public const PROTOCOL_HTTP = 'http';

    public const PROTOCOL_HTTPS = 'https';

    public const PROTOCOL_AUTO = 'auto';

    public const TRANSPORT_RELAY_SINGLE = 'relay-single';

    public const TRANSPORT_RELAY_CHUNK = 'relay-chunk';

    public const TRANSPORT_DIRECT_SINGLE = 'direct-single';

    public const TRANSPORT_DIRECT_MULTIPART = 'direct-multipart';

    /**
     * @return array<int, string>
     */
    public static function drivers(): array
    {
        return [
            self::DRIVER_LOCAL,
            self::DRIVER_OSS,
            self::DRIVER_QINIU,
            self::DRIVER_COS,
            self::DRIVER_ALIST,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function remoteDrivers(): array
    {
        return [
            self::DRIVER_OSS,
            self::DRIVER_QINIU,
            self::DRIVER_COS,
            self::DRIVER_ALIST,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function categories(): array
    {
        return [
            self::CATEGORY_IMAGE,
            self::CATEGORY_FILE,
            self::CATEGORY_VIDEO,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultConfig(): array
    {
        return [
            'active_mode' => self::DRIVER_LOCAL,
            'common' => [
                'name_type' => self::NAME_TYPE_HASH,
                'link_type' => self::LINK_TYPE_FULL_URL,
                'allow_exts' => implode(',', [
                    'doc',
                    'docx',
                    'gif',
                    'ico',
                    'jpg',
                    'jpeg',
                    'json',
                    'mp3',
                    'mp4',
                    'p12',
                    'pdf',
                    'pem',
                    'png',
                    'rar',
                    'txt',
                    'webm',
                    'webp',
                    'xls',
                    'xlsx',
                    'zip',
                ]),
                'protocol' => self::PROTOCOL_HTTPS,
                'max_size_mb' => 1024,
                'chunk_threshold_mb' => 20,
                'multipart_threshold_mb' => 20,
                'part_size_mb' => 5,
            ],
            'drivers' => [
                self::DRIVER_LOCAL => [
                    'enabled' => true,
                    'title' => '本地存储',
                    'storage_path' => 'upload',
                    'domain' => '',
                ],
                self::DRIVER_OSS => [
                    'enabled' => false,
                    'title' => '阿里云 OSS',
                    'region' => 'cn-hangzhou',
                    'endpoint' => 'oss-cn-hangzhou.aliyuncs.com',
                    'bucket' => '',
                    'domain' => '',
                    'access_id_encrypted' => '',
                    'access_secret_encrypted' => '',
                ],
                self::DRIVER_QINIU => [
                    'enabled' => false,
                    'title' => '七牛云',
                    'region' => 'z0',
                    'bucket' => '',
                    'domain' => '',
                    'access_key_encrypted' => '',
                    'secret_key_encrypted' => '',
                ],
                self::DRIVER_COS => [
                    'enabled' => false,
                    'title' => '腾讯云 COS',
                    'region' => 'ap-beijing',
                    'bucket' => '',
                    'domain' => '',
                    'secret_id_encrypted' => '',
                    'secret_key_encrypted' => '',
                ],
                self::DRIVER_ALIST => [
                    'enabled' => false,
                    'title' => 'AList',
                    'endpoint' => '',
                    'root' => '/upload',
                    'domain' => '',
                    'public_path' => '/d',
                    'username' => '',
                    'password_encrypted' => '',
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public static function normalizeRuntimeConfig(array $config): array
    {
        $defaults = self::defaultConfig();
        $drivers = is_array($config['drivers'] ?? null) ? $config['drivers'] : [];
        $common = is_array($config['common'] ?? null) ? $config['common'] : [];

        $config['common'] = array_merge($defaults['common'], $common);

        $localConfig = is_array($drivers[self::DRIVER_LOCAL] ?? null) ? $drivers[self::DRIVER_LOCAL] : [];
        $config['drivers'][self::DRIVER_LOCAL] = array_merge($defaults['drivers'][self::DRIVER_LOCAL], $localConfig, [
            'enabled' => true,
        ]);

        foreach (self::remoteDrivers() as $driver) {
            $driverConfig = is_array($drivers[$driver] ?? null) ? $drivers[$driver] : [];
            $config['drivers'][$driver] = array_merge($defaults['drivers'][$driver], $driverConfig);

            if (!self::isRemoteDriverConfigured($driver, $config['drivers'][$driver])) {
                $config['drivers'][$driver]['enabled'] = false;
            }
        }

        $activeMode = (string)($config['active_mode'] ?? self::DRIVER_LOCAL);
        if (!in_array($activeMode, self::drivers(), true)) {
            $config['active_mode'] = self::DRIVER_LOCAL;
            return $config;
        }

        $activeDriverConfig = is_array($config['drivers'][$activeMode] ?? null) ? $config['drivers'][$activeMode] : [];
        if (
            $activeMode !== self::DRIVER_LOCAL
            && (
                !self::toBool($activeDriverConfig['enabled'] ?? false)
                || !self::isRemoteDriverConfigured($activeMode, $activeDriverConfig)
            )
        ) {
            $config['active_mode'] = self::DRIVER_LOCAL;
        }

        return $config;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function categoryExtensions(): array
    {
        return [
            self::CATEGORY_IMAGE => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'ico'],
            self::CATEGORY_FILE => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar', '7z', 'txt', 'csv', 'json', 'pem', 'p12', 'mp3'],
            self::CATEGORY_VIDEO => ['mp4', 'mov', 'webm', 'm4v', 'avi'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function extensionMimePatterns(): array
    {
        return [
            'jpg' => ['image/jpeg', 'image/pjpeg'],
            'jpeg' => ['image/jpeg', 'image/pjpeg'],
            'png' => ['image/png', 'image/x-png'],
            'gif' => ['image/gif'],
            'webp' => ['image/webp'],
            'bmp' => ['image/bmp', 'image/x-ms-bmp'],
            'ico' => ['image/x-icon', 'image/vnd.microsoft.icon'],
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword', 'application/CDFV2', 'application/octet-stream'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
            'xls' => ['application/vnd.ms-excel', 'application/CDFV2', 'application/octet-stream'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
            'zip' => ['application/zip', 'application/x-zip', 'application/x-zip-compressed', 'multipart/x-zip', 'application/octet-stream'],
            'rar' => ['application/vnd.rar', 'application/x-rar', 'application/x-rar-compressed', 'application/octet-stream'],
            '7z' => ['application/x-7z-compressed', 'application/octet-stream'],
            'txt' => ['text/plain', 'application/octet-stream'],
            'csv' => ['text/csv', 'application/csv', 'text/plain', 'application/vnd.ms-excel', 'application/octet-stream'],
            'json' => ['application/json', 'text/json', 'text/plain'],
            'pem' => ['application/x-pem-file', 'application/octet-stream', 'text/plain'],
            'p12' => ['application/x-pkcs12', 'application/octet-stream'],
            'mp3' => ['audio/mpeg', 'audio/mp3', 'application/octet-stream'],
            'mp4' => ['video/mp4', 'application/mp4'],
            'mov' => ['video/quicktime', 'video/mov'],
            'webm' => ['video/webm'],
            'm4v' => ['video/x-m4v', 'video/mp4'],
            'avi' => ['video/x-msvideo', 'video/avi', 'video/msvideo'],
        ];
    }

    public static function storageMode(string $driver): int
    {
        return match ($driver) {
            self::DRIVER_OSS => SystemFile::STORAGE_MODE_OSS,
            self::DRIVER_QINIU => SystemFile::STORAGE_MODE_QINIU,
            self::DRIVER_COS => SystemFile::STORAGE_MODE_COS,
            self::DRIVER_ALIST => SystemFile::STORAGE_MODE_ALIST,
            default => SystemFile::STORAGE_MODE_LOCAL,
        };
    }

    public static function supportsDirectUpload(string $driver): bool
    {
        return !in_array($driver, [self::DRIVER_LOCAL, self::DRIVER_ALIST], true);
    }

    public static function supportsMultipartUpload(string $driver): bool
    {
        return $driver === self::DRIVER_OSS;
    }

    /**
     * @return array<int, string>
     */
    public static function driverSecretFields(string $driver): array
    {
        return match ($driver) {
            self::DRIVER_OSS => ['access_id', 'access_secret'],
            self::DRIVER_QINIU => ['access_key', 'secret_key'],
            self::DRIVER_COS => ['secret_id', 'secret_key'],
            self::DRIVER_ALIST => ['password'],
            default => [],
        };
    }

    /**
     * @return array<int, string>
     */
    public static function driverRequiredFields(string $driver): array
    {
        return match ($driver) {
            self::DRIVER_OSS => ['region', 'endpoint', 'bucket', 'domain'],
            self::DRIVER_QINIU => ['region', 'bucket', 'domain'],
            self::DRIVER_COS => ['region', 'bucket', 'domain'],
            self::DRIVER_ALIST => ['endpoint', 'root', 'username'],
            default => [],
        };
    }

    public static function isSensitiveField(string $field): bool
    {
        return in_array($field, ['access_id', 'access_secret', 'access_key', 'secret_id', 'secret_key', 'password'], true);
    }

    public static function encryptedField(string $field): string
    {
        return $field . '_encrypted';
    }

    public static function normalizePath(string $path): string
    {
        $segments = array_filter(array_map(
            static fn (string $segment): string => trim($segment),
            preg_split('#[\\\\/]+#', trim($path)) ?: []
        ), static fn (string $segment): bool => $segment !== '' && $segment !== '.' && $segment !== '..');

        return implode('/', $segments);
    }

    public static function normalizeBaseUrl(string $path): string
    {
        $normalized = self::normalizePath($path);
        return '/' . ltrim($normalized, '/');
    }

    public static function normalizeSchema(string $schema): string
    {
        $schema = trim($schema);
        if ($schema === '') {
            return 'https://';
        }

        if (!preg_match('#^[a-z][a-z0-9+.-]*://$#i', $schema)) {
            $schema = rtrim($schema, ':/') . '://';
        }

        return rtrim($schema, ':/') . '://';
    }

    public static function normalizeProtocol(string $protocol): string
    {
        return match (strtolower(trim($protocol))) {
            self::PROTOCOL_HTTP => self::PROTOCOL_HTTP,
            self::PROTOCOL_AUTO => self::PROTOCOL_AUTO,
            default => self::PROTOCOL_HTTPS,
        };
    }

    public static function normalizeLinkType(string $linkType): string
    {
        return match (strtolower(trim($linkType))) {
            self::LINK_TYPE_RELATIVE_URL => self::LINK_TYPE_RELATIVE_URL,
            self::LINK_TYPE_STORAGE_PATH => self::LINK_TYPE_STORAGE_PATH,
            default => self::LINK_TYPE_FULL_URL,
        };
    }

    public static function normalizeNameType(string $nameType): string
    {
        return match (strtolower(trim($nameType))) {
            self::NAME_TYPE_DATE_RANDOM => self::NAME_TYPE_DATE_RANDOM,
            default => self::NAME_TYPE_HASH,
        };
    }

    public static function normalizeDomain(string $domain): string
    {
        $domain = trim($domain);
        $domain = preg_replace('#^[a-z][a-z0-9+.-]*://#i', '', $domain) ?: '';
        return trim($domain, '/');
    }

    public static function normalizeHttpEndpoint(string $endpoint): string
    {
        $endpoint = trim($endpoint);
        if ($endpoint === '') {
            return '';
        }

        if (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $endpoint)) {
            $endpoint = 'http://' . ltrim($endpoint, '/');
        }

        $parsed = parse_url($endpoint);
        if (!is_array($parsed) || empty($parsed['host'])) {
            return rtrim($endpoint, '/');
        }

        $normalized = sprintf('%s://%s', $parsed['scheme'] ?? 'http', $parsed['host']);
        if (isset($parsed['port'])) {
            $normalized .= ':' . $parsed['port'];
        }

        $path = rtrim((string)($parsed['path'] ?? ''), '/');
        if ($path !== '' && str_ends_with($path, '/api')) {
            $path = substr($path, 0, -4);
        }
        if ($path !== '') {
            $normalized .= $path;
        }

        return rtrim($normalized, '/');
    }

    /**
     * @param array<string, mixed> $common
     * @return array<int, string>
     */
    public static function allowedExtensions(array $common): array
    {
        $value = is_array($common['allow_exts'] ?? null)
            ? implode(',', array_map(static fn (mixed $item): string => (string)$item, $common['allow_exts']))
            : (string)($common['allow_exts'] ?? '');

        return array_values(array_unique(array_filter(array_map(
            static function (string $item): string {
                $item = strtolower(trim($item));
                return ltrim($item, '.');
            },
            explode(',', $value)
        ))));
    }

    public static function normalizeAllowExts(string $allowExts): string
    {
        return implode(',', self::allowedExtensions(['allow_exts' => $allowExts]));
    }

    public static function resolveCategory(?string $mode, ?string $mimeType = null, ?string $suffix = null): string
    {
        $mode = strtolower(trim((string)$mode));
        if (in_array($mode, self::categories(), true)) {
            return $mode;
        }

        $mimeType = strtolower(trim((string)$mimeType));
        if ($mimeType !== '') {
            if (str_starts_with($mimeType, 'image/')) {
                return self::CATEGORY_IMAGE;
            }
            if (str_starts_with($mimeType, 'video/')) {
                return self::CATEGORY_VIDEO;
            }
        }

        $suffix = strtolower(trim((string)$suffix));
        foreach (self::categoryExtensions() as $category => $extensions) {
            if (in_array($suffix, $extensions, true)) {
                return $category;
            }
        }

        return self::CATEGORY_FILE;
    }

    public static function isCategoryExtensionAllowed(string $category, string $suffix): bool
    {
        $extensions = self::categoryExtensions()[$category] ?? [];
        return $extensions === [] || in_array(strtolower($suffix), $extensions, true);
    }

    public static function mask(string $value): string
    {
        $value = trim($value);
        $length = mb_strlen($value);
        if ($length <= 6) {
            return $length > 0 ? str_repeat('*', $length) : '';
        }

        return sprintf(
            '%s%s%s',
            mb_substr($value, 0, 3),
            str_repeat('*', max(4, $length - 6)),
            mb_substr($value, -3)
        );
    }

    private static function toBool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL) || (int)$value === 1;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function isRemoteDriverConfigured(string $driver, array $config): bool
    {
        if ($driver === self::DRIVER_LOCAL) {
            return true;
        }

        foreach (self::driverRequiredFields($driver) as $field) {
            if (trim((string)($config[$field] ?? '')) === '') {
                return false;
            }
        }

        foreach (self::driverSecretFields($driver) as $field) {
            if (trim((string)($config[self::encryptedField($field)] ?? '')) === '') {
                return false;
            }
        }

        return true;
    }
}
