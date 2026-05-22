<?php

declare(strict_types=1);

namespace System\Support\Storage;

use Library\Exception\ErrorResponseException;
use System\Support\UploadDriver;

final class AlistStorage extends AbstractRemoteStorage
{
    /**
     * @var array<string, string>
     */
    private static array $tokenCache = [];

    /**
     * @var array<string, true>
     */
    private array $directoryCache = [];

    /**
     * @return array<int, array<string, string>>
     */
    public static function region(): array
    {
        return [];
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function set(string $name, string $file, bool $safe = false, ?string $attname = null, array $options = []): array
    {
        $key = $this->normalizeKey($name);
        $absolutePath = $this->absolutePath($key);
        $this->ensureDirectoryExists(dirname($absolutePath));

        $mimeType = trim((string)($options['mime_type'] ?? 'application/octet-stream'));
        $response = $this->request('PUT', $this->apiUrl('/api/fs/put'), [
            'headers' => array_merge($this->authorizationHeaders(), [
                'As-Task' => 'false',
                'Content-Length' => (string)strlen($file),
                'Content-Type' => $mimeType,
                'File-Path' => rawurlencode($absolutePath),
            ]),
            'body' => $file,
        ]);

        $this->assertApiSuccess($this->decodeResponse($response));

        return $this->buildInfo($key, [
            'mime_type' => $mimeType,
            'size_byte' => strlen($file),
        ]);
    }

    /**
     * 读取 AList 对象内容。
     */
    public function get(string $name, bool $safe = false): string
    {
        $meta = $this->fetchMetadata($this->absolutePath($this->normalizeKey($name)));
        $rawUrl = trim((string)($meta['raw_url'] ?? ''));
        if ($rawUrl === '') {
            throw new ErrorResponseException('文件不存在');
        }

        $response = $this->request('GET', $this->absoluteUrl($rawUrl), [
            'headers' => $this->resolveRawUrlHeaders($meta),
        ]);
        if ($response->getStatusCode() === 404) {
            throw new ErrorResponseException('文件不存在');
        }

        $this->ensureSuccessful($response, [200]);
        return (string)$response->getBody();
    }

    /**
     * 删除 AList 对象。
     */
    public function del(string $name, bool $safe = false): bool
    {
        $key = $this->normalizeKey($name);
        $absolutePath = $this->absolutePath($key);
        if (!$this->has($key)) {
            return true;
        }

        $payload = [
            'dir' => $this->normalizeAbsolutePath(dirname($absolutePath)),
            'names' => [basename($absolutePath)],
        ];
        $response = $this->request('POST', $this->apiUrl('/api/fs/remove'), [
            'headers' => $this->jsonHeaders(),
            'json' => $payload,
        ]);
        $this->assertApiSuccess($this->decodeResponse($response));

        return true;
    }

    /**
     * 判断 AList 对象是否存在。
     */
    public function has(string $name, bool $safe = false): bool
    {
        return $this->fetchMetadata($this->absolutePath($this->normalizeKey($name)), false) !== null;
    }

    /**
     * 生成 AList 对象访问地址。
     */
    public function url(string $name, bool $safe = false, ?string $attname = null): string
    {
        $key = $this->normalizeKey($name);
        $absolutePath = trim($this->absolutePath($key), '/');
        $publicPath = trim((string)($this->config['public_path'] ?? '/d'), '/');
        $publicRelative = trim(($publicPath !== '' ? $publicPath . '/' : '') . $absolutePath, '/');

        if ($this->linkType() === UploadDriver::LINK_TYPE_STORAGE_PATH) {
            return $absolutePath;
        }

        if ($this->linkType() === UploadDriver::LINK_TYPE_RELATIVE_URL) {
            return '/' . $publicRelative;
        }

        $domain = UploadDriver::normalizeDomain((string)($this->config['domain'] ?? ''));
        if ($domain !== '') {
            $url = $this->protocolPrefix() . $domain . '/' . $publicRelative;
        } else {
            $url = rtrim($this->endpoint(), '/') . '/' . $publicRelative;
        }

        if ($attname === null || trim($attname) === '') {
            return $url;
        }

        return $this->appendQuery($url, [
            'attname' => $this->normalizeAttachmentName($attname, basename($key)),
        ]);
    }

    /**
     * 返回 AList 存储路径格式。
     */
    public function path(string $name, bool $safe = false): string
    {
        return trim($this->absolutePath($this->normalizeKey($name)), '/');
    }

    /**
     * @return array<string, mixed>
     */
    public function info(string $name, bool $safe = false, ?string $attname = null): array
    {
        $key = $this->normalizeKey($name);
        $meta = $this->fetchMetadata($this->absolutePath($key));

        return $this->buildInfo($key, [
            'etag' => (string)($meta['hash_info']['md5'] ?? $meta['hashinfo'] ?? ''),
            'last_modified' => (string)($meta['modified'] ?? ''),
            'mime_type' => $this->guessMimeType($key),
            'size_byte' => (int)($meta['size'] ?? 0),
        ]);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function upload(array $context = []): array
    {
        return ['supported' => false];
    }

    /**
     * 获取 AList 服务端地址。
     */
    private function endpoint(): string
    {
        return UploadDriver::normalizeHttpEndpoint((string)($this->config['endpoint'] ?? ''));
    }

    /**
     * 拼接 AList API 地址。
     */
    private function apiUrl(string $path): string
    {
        return rtrim($this->endpoint(), '/') . $path;
    }

    /**
     * @return array<string, string>
     */
    private function authorizationHeaders(): array
    {
        return ['Authorization' => $this->token()];
    }

    /**
     * @return array<string, string>
     */
    private function jsonHeaders(): array
    {
        return array_merge($this->authorizationHeaders(), ['Content-Type' => 'application/json']);
    }

    /**
     * 获取并缓存 AList 访问 token。
     */
    private function token(): string
    {
        $cacheKey = sha1(implode('|', [
            $this->endpoint(),
            (string)($this->config['username'] ?? ''),
            (string)($this->config['password'] ?? ''),
        ]));

        if (isset(self::$tokenCache[$cacheKey]) && self::$tokenCache[$cacheKey] !== '') {
            return self::$tokenCache[$cacheKey];
        }

        $response = $this->request('POST', $this->apiUrl('/api/auth/login'), [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'password' => (string)($this->config['password'] ?? ''),
                'username' => (string)($this->config['username'] ?? ''),
            ],
        ]);
        $payload = $this->decodeResponse($response);
        $this->assertApiSuccess($payload);

        $token = trim((string)($payload['data']['token'] ?? ''));
        if ($token === '') {
            throw new ErrorResponseException('AList 登录失败，未返回 token');
        }

        self::$tokenCache[$cacheKey] = $token;
        return $token;
    }

    /**
     * 确保目录存在，不存在时递归创建。
     */
    private function ensureDirectoryExists(string $directory): void
    {
        $directory = $this->normalizeAbsolutePath($directory);
        if ($directory === '/' || isset($this->directoryCache[$directory])) {
            return;
        }

        $parent = $this->normalizeAbsolutePath(dirname($directory));
        if ($parent !== $directory && $parent !== '/') {
            $this->ensureDirectoryExists($parent);
        }

        if ($this->fetchMetadata($directory, false) !== null) {
            $this->directoryCache[$directory] = true;
            return;
        }

        $response = $this->request('POST', $this->apiUrl('/api/fs/mkdir'), [
            'headers' => $this->jsonHeaders(),
            'json' => ['path' => $directory],
        ]);
        $payload = $this->decodeResponse($response);
        try {
            $this->assertApiSuccess($payload);
        } catch (ErrorResponseException $exception) {
            if (!$this->isAlreadyExistsMessage($exception->getMessage())) {
                throw $exception;
            }
        }

        $this->directoryCache[$directory] = true;
    }

    /**
     * @return null|array<string, mixed>
     */
    private function fetchMetadata(string $absolutePath, bool $throwWhenMissing = true): ?array
    {
        $response = $this->request('POST', $this->apiUrl('/api/fs/get'), [
            'headers' => $this->jsonHeaders(),
            'json' => [
                'password' => '',
                'path' => $absolutePath,
                'refresh' => false,
            ],
        ]);
        $payload = $this->decodeResponse($response);

        if ((int)($payload['code'] ?? 500) === 200) {
            $data = $payload['data'] ?? null;
            return is_array($data) ? $data : null;
        }

        $message = trim((string)($payload['message'] ?? $payload['info'] ?? ''));
        if (!$throwWhenMissing && $this->isNotFoundMessage($message)) {
            return null;
        }

        if ($this->isNotFoundMessage($message)) {
            throw new ErrorResponseException('文件不存在');
        }

        throw new ErrorResponseException($message !== '' ? $message : 'AList 获取文件信息失败');
    }

    /**
     * 断言 AList API 返回成功状态。
     *
     * @param array<string, mixed> $payload
     */
    private function assertApiSuccess(array $payload): void
    {
        if ((int)($payload['code'] ?? 500) === 200) {
            return;
        }

        $message = trim((string)($payload['message'] ?? $payload['info'] ?? '远程存储请求失败'));
        if ($this->isNotFoundMessage($message)) {
            throw new ErrorResponseException('文件不存在');
        }

        throw new ErrorResponseException($message);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(\Psr\Http\Message\ResponseInterface $response): array
    {
        $this->ensureSuccessful($response, [200]);
        $data = json_decode((string)$response->getBody(), true);
        if (!is_array($data)) {
            throw new ErrorResponseException('AList 返回了无效响应');
        }

        return $data;
    }

    /**
     * 基于根目录拼接对象绝对路径。
     */
    private function absolutePath(string $key): string
    {
        $root = trim((string)($this->config['root'] ?? '/'), '/');
        return $this->normalizeAbsolutePath(($root !== '' ? '/' . $root : '') . '/' . ltrim($key, '/'));
    }

    /**
     * 规范化绝对路径格式。
     */
    private function normalizeAbsolutePath(string $path): string
    {
        $normalized = UploadDriver::normalizePath($path);
        return $normalized === '' ? '/' : '/' . ltrim($normalized, '/');
    }

    /**
     * 将相对地址转换为可访问绝对 URL。
     */
    private function absoluteUrl(string $url): string
    {
        if (preg_match('#^https?://#i', $url) || str_starts_with($url, '//')) {
            return $url;
        }

        $endpoint = parse_url($this->endpoint());
        $origin = sprintf('%s://%s', $endpoint['scheme'] ?? 'http', $endpoint['host'] ?? '');
        if (isset($endpoint['port'])) {
            $origin .= ':' . $endpoint['port'];
        }

        return $origin . '/' . ltrim($url, '/');
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, string>
     */
    private function resolveRawUrlHeaders(array $meta): array
    {
        $header = $meta['header'] ?? null;
        if (is_array($header)) {
            return array_map(static fn (mixed $value): string => (string)$value, $header);
        }

        if (!is_string($header) || trim($header) === '') {
            return [];
        }

        $decoded = json_decode($header, true);
        if (is_array($decoded)) {
            return array_map(static fn (mixed $value): string => (string)$value, $decoded);
        }

        return [];
    }

    /**
     * 根据对象后缀推断 MIME。
     */
    private function guessMimeType(string $key): string
    {
        $suffix = strtolower(pathinfo($key, PATHINFO_EXTENSION));
        if ($suffix === '') {
            return 'application/octet-stream';
        }

        $patterns = UploadDriver::extensionMimePatterns();
        return (string)($patterns[$suffix][0] ?? 'application/octet-stream');
    }

    /**
     * 判断错误消息是否表示资源不存在。
     */
    private function isNotFoundMessage(string $message): bool
    {
        $message = strtolower(trim($message));
        if ($message === '') {
            return false;
        }

        return str_contains($message, 'not found')
            || str_contains($message, 'object not found')
            || str_contains($message, 'does not exist')
            || str_contains($message, '不存在');
    }

    /**
     * 判断错误消息是否表示资源已存在。
     */
    private function isAlreadyExistsMessage(string $message): bool
    {
        $message = strtolower(trim($message));
        if ($message === '') {
            return false;
        }

        return str_contains($message, 'already exists')
            || str_contains($message, 'file exists')
            || str_contains($message, '已存在');
    }
}
