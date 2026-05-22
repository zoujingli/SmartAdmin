<?php

declare(strict_types=1);

namespace System\Support\Storage;

use Library\Exception\ErrorResponseException;
use System\Support\UploadDriver;

final class QiniuStorage extends AbstractRemoteStorage
{
    /**
     * @return array<int, array<string, string>>
     */
    public static function region(): array
    {
        return [
            ['label' => '华东-浙江', 'value' => 'z0'],
            ['label' => '华东-浙江2', 'value' => 'cn-east-2'],
            ['label' => '华北', 'value' => 'z1'],
            ['label' => '华南', 'value' => 'z2'],
            ['label' => '北美', 'value' => 'na0'],
            ['label' => '亚太-新加坡', 'value' => 'as0'],
            ['label' => '亚太-河内', 'value' => 'ap-southeast-2'],
            ['label' => '亚太-胡志明', 'value' => 'ap-southeast-3'],
        ];
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function set(string $name, string $file, bool $safe = false, ?string $attname = null, array $options = []): array
    {
        $key = $this->normalizeKey($name);
        $token = $this->uploadToken($key, max(60, (int)($options['expires'] ?? 3600)));
        $mimeType = trim((string)($options['mime_type'] ?? 'application/octet-stream'));

        $response = $this->request('POST', $this->uploadHost(), [
            'multipart' => [
                ['name' => 'token', 'contents' => $token],
                ['name' => 'key', 'contents' => $key],
                ['name' => 'file', 'contents' => $file, 'filename' => basename($key), 'headers' => ['Content-Type' => $mimeType]],
            ],
        ]);

        $this->ensureSuccessful($response, [200]);
        return $this->info($key);
    }

    /**
     * 读取七牛对象内容。
     */
    public function get(string $name, bool $safe = false): string
    {
        $response = $this->request('GET', $this->url($name));
        if ($response->getStatusCode() === 404) {
            throw new ErrorResponseException('文件不存在');
        }

        $this->ensureSuccessful($response, [200]);
        return (string)$response->getBody();
    }

    /**
     * 删除七牛对象。
     */
    public function del(string $name, bool $safe = false): bool
    {
        $entry = $this->entryUri($this->normalizeKey($name));
        $path = '/delete/' . $entry;
        $response = $this->request('POST', 'https://rs.qiniuapi.com' . $path, [
            'headers' => $this->managementHeaders($path),
        ]);

        return in_array($response->getStatusCode(), [200, 612], true);
    }

    /**
     * 判断七牛对象是否存在。
     */
    public function has(string $name, bool $safe = false): bool
    {
        $key = $this->normalizeKey($name);
        $path = '/stat/' . $this->entryUri($key);
        $response = $this->request('GET', 'https://rs.qiniuapi.com' . $path, [
            'headers' => $this->managementHeaders($path),
        ]);
        if ($response->getStatusCode() === 612) {
            return false;
        }

        $this->ensureSuccessful($response, [200]);
        return true;
    }

    /**
     * 生成七牛对象访问地址。
     */
    public function url(string $name, bool $safe = false, ?string $attname = null): string
    {
        $key = $this->normalizeKey($name);
        if ($this->linkType() === UploadDriver::LINK_TYPE_STORAGE_PATH) {
            return $key;
        }

        if ($this->linkType() === UploadDriver::LINK_TYPE_RELATIVE_URL) {
            return '/' . $key;
        }

        $domain = UploadDriver::normalizeDomain((string)($this->config['domain'] ?? ''));
        if ($domain === '') {
            return '/' . $key;
        }

        $url = $this->protocolPrefix() . $domain . '/' . $this->encodeKey($key);
        if ($attname === null || trim($attname) === '') {
            return $url;
        }

        return $this->appendQuery($url, [
            'attname' => $this->normalizeAttachmentName($attname, basename($key)),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function info(string $name, bool $safe = false, ?string $attname = null): array
    {
        $key = $this->normalizeKey($name);
        $path = '/stat/' . $this->entryUri($key);
        $response = $this->request('GET', 'https://rs.qiniuapi.com' . $path, [
            'headers' => $this->managementHeaders($path),
        ]);
        if ($response->getStatusCode() === 612) {
            throw new ErrorResponseException('文件不存在');
        }

        $this->ensureSuccessful($response, [200]);
        $payload = json_decode((string)$response->getBody(), true);
        if (!is_array($payload)) {
            throw new ErrorResponseException('读取七牛文件信息失败');
        }

        return $this->buildInfo($key, [
            'mime_type' => (string)($payload['mimeType'] ?? 'application/octet-stream'),
            'size_byte' => (int)($payload['fsize'] ?? 0),
            'hash' => (string)($payload['hash'] ?? ''),
            'last_modified' => isset($payload['putTime']) ? date('Y-m-d H:i:s', (int)floor(((int)$payload['putTime']) / 10000000)) : '',
        ]);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function upload(array $context = []): array
    {
        $key = $this->normalizeKey((string)($context['name'] ?? ''));
        $expires = max(60, (int)($context['expires'] ?? 3600));

        return [
            'supported' => true,
            'method' => 'POST',
            'upload_url' => $this->uploadHost(),
            'form_fields' => [
                'token' => $this->uploadToken($key, $expires),
                'key' => $key,
            ],
            'file_field' => 'file',
        ];
    }

    /**
     * 解析七牛上传入口域名。
     */
    private function uploadHost(): string
    {
        return 'https://' . match ((string)($this->config['region'] ?? 'z0')) {
            'cn-east-2' => 'up-cn-east-2.qiniup.com',
            'z1' => 'up-z1.qiniup.com',
            'z2' => 'up-z2.qiniup.com',
            'na0' => 'up-na0.qiniup.com',
            'as0' => 'up-as0.qiniup.com',
            'ap-southeast-2' => 'up-ap-southeast-2.qiniup.com',
            'ap-southeast-3' => 'up-ap-southeast-3.qiniup.com',
            default => 'up.qiniup.com',
        };
    }

    /**
     * 生成七牛上传凭证。
     */
    private function uploadToken(string $key, int $expires): string
    {
        $policy = [
            'scope' => (string)$this->config['bucket'] . ':' . $key,
            'deadline' => time() + $expires,
        ];
        $encodedPolicy = $this->urlSafeBase64(json_encode($policy, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
        $sign = $this->urlSafeBase64(hash_hmac('sha1', $encodedPolicy, (string)$this->config['secret_key'], true));

        return sprintf('%s:%s:%s', (string)$this->config['access_key'], $sign, $encodedPolicy);
    }

    /**
     * @return array<string, string>
     */
    private function managementHeaders(string $path): array
    {
        $sign = $this->urlSafeBase64(hash_hmac('sha1', $path . "\n", (string)$this->config['secret_key'], true));
        return [
            'Authorization' => sprintf('QBox %s:%s', (string)$this->config['access_key'], $sign),
        ];
    }

    /**
     * 生成七牛资源 entry URI。
     */
    private function entryUri(string $key): string
    {
        return $this->urlSafeBase64((string)$this->config['bucket'] . ':' . $key);
    }

    /**
     * URL 安全的 Base64 编码。
     */
    private function urlSafeBase64(string $value): string
    {
        return str_replace(['+', '/'], ['-', '_'], base64_encode($value));
    }

    /**
     * 对对象键逐段 URL 编码。
     */
    private function encodeKey(string $key): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $key)));
    }
}
