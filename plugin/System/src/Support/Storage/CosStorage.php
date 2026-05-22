<?php

declare(strict_types=1);

namespace System\Support\Storage;

use Library\Exception\ErrorResponseException;
use System\Support\UploadDriver;

final class CosStorage extends AbstractRemoteStorage
{
    /**
     * @return array<int, array<string, string>>
     */
    public static function region(): array
    {
        return [
            ['label' => '北京', 'value' => 'ap-beijing'],
            ['label' => '南京', 'value' => 'ap-nanjing'],
            ['label' => '上海', 'value' => 'ap-shanghai'],
            ['label' => '广州', 'value' => 'ap-guangzhou'],
            ['label' => '成都', 'value' => 'ap-chengdu'],
            ['label' => '重庆', 'value' => 'ap-chongqing'],
            ['label' => '中国香港', 'value' => 'ap-hongkong'],
            ['label' => '新加坡', 'value' => 'ap-singapore'],
            ['label' => '雅加达', 'value' => 'ap-jakarta'],
            ['label' => '首尔', 'value' => 'ap-seoul'],
            ['label' => '曼谷', 'value' => 'ap-bangkok'],
            ['label' => '东京', 'value' => 'ap-tokyo'],
            ['label' => '利雅得', 'value' => 'me-saudi-arabia'],
            ['label' => '硅谷（美西）', 'value' => 'na-siliconvalley'],
            ['label' => '弗吉尼亚（美东）', 'value' => 'na-ashburn'],
            ['label' => '圣保罗', 'value' => 'sa-saopaulo'],
            ['label' => '法兰克福', 'value' => 'eu-frankfurt'],
        ];
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function set(string $name, string $file, bool $safe = false, ?string $attname = null, array $options = []): array
    {
        $key = $this->normalizeKey($name);
        $mimeType = trim((string)($options['mime_type'] ?? 'application/octet-stream'));
        $headers = ['content-type' => $mimeType];
        if ($attname !== null && trim($attname) !== '') {
            $headers['content-disposition'] = $this->buildAttachmentDisposition($attname);
        }
        $response = $this->request('PUT', $this->serviceUrl($key), [
            'headers' => $this->signedHeaders('PUT', $key, [], $headers),
            'body' => $file,
        ]);
        $this->ensureSuccessful($response, [200]);

        return $this->info($key);
    }

    /**
     * 读取 COS 对象内容。
     */
    public function get(string $name, bool $safe = false): string
    {
        $key = $this->normalizeKey($name);
        $response = $this->request('GET', $this->serviceUrl($key), [
            'headers' => $this->signedHeaders('GET', $key),
        ]);
        if ($response->getStatusCode() === 404) {
            throw new ErrorResponseException('文件不存在');
        }

        $this->ensureSuccessful($response, [200]);
        return (string)$response->getBody();
    }

    /**
     * 删除 COS 对象。
     */
    public function del(string $name, bool $safe = false): bool
    {
        $key = $this->normalizeKey($name);
        $response = $this->request('DELETE', $this->serviceUrl($key), [
            'headers' => $this->signedHeaders('DELETE', $key),
        ]);

        return in_array($response->getStatusCode(), [204, 404], true);
    }

    /**
     * 判断 COS 对象是否存在。
     */
    public function has(string $name, bool $safe = false): bool
    {
        $key = $this->normalizeKey($name);
        $response = $this->request('HEAD', $this->serviceUrl($key), [
            'headers' => $this->signedHeaders('HEAD', $key),
        ]);

        return $response->getStatusCode() === 200;
    }

    /**
     * 生成 COS 对象访问地址。
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
            $domain = $this->serviceHost();
        }

        $url = $this->protocolPrefix() . $domain . '/' . $this->encodeKey($key);
        if ($attname === null || trim($attname) === '') {
            return $url;
        }

        return $this->appendQuery($url, [
            'response-content-disposition' => $this->buildAttachmentDisposition($attname),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function info(string $name, bool $safe = false, ?string $attname = null): array
    {
        $key = $this->normalizeKey($name);
        $response = $this->request('HEAD', $this->serviceUrl($key), [
            'headers' => $this->signedHeaders('HEAD', $key),
        ]);
        if ($response->getStatusCode() === 404) {
            throw new ErrorResponseException('文件不存在');
        }

        $this->ensureSuccessful($response, [200]);
        $headers = $this->headersToMap($response);

        return $this->buildInfo($key, [
            'mime_type' => (string)($headers['content-type'] ?? 'application/octet-stream'),
            'size_byte' => (int)($headers['content-length'] ?? 0),
            'etag' => trim((string)($headers['etag'] ?? ''), '"'),
            'last_modified' => (string)($headers['last-modified'] ?? ''),
        ]);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function upload(array $context = []): array
    {
        $key = $this->normalizeKey((string)($context['name'] ?? ''));
        $mimeType = trim((string)($context['mime_type'] ?? 'application/octet-stream'));
        $headers = ['content-type' => $mimeType];
        $downloadName = trim((string)($context['download_name'] ?? ''));
        if ($downloadName !== '') {
            $headers['content-disposition'] = $this->buildAttachmentDisposition($downloadName);
        }
        $signedHeaders = $this->signedHeaders('PUT', $key, [], $headers, max(60, (int)($context['expires'] ?? 3600)));
        unset($signedHeaders['host']);

        return [
            'supported' => true,
            'method' => 'PUT',
            'upload_url' => $this->serviceUrl($key),
            'headers' => $signedHeaders,
        ];
    }

    /**
     * 拼接 COS 服务 URL。
     *
     * @param array<string, string> $query
     */
    private function serviceUrl(string $key, array $query = []): string
    {
        $url = 'https://' . $this->serviceHost() . '/' . $this->encodeKey($key);
        if ($query === []) {
            return $url;
        }

        return $url . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * 获取 COS 服务 host。
     */
    private function serviceHost(): string
    {
        return sprintf(
            '%s.cos.%s.myqcloud.com',
            (string)$this->config['bucket'],
            (string)$this->config['region']
        );
    }

    /**
     * @param array<string, string> $query
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private function signedHeaders(
        string $method,
        string $key,
        array $query = [],
        array $headers = [],
        int $expires = 3600
    ): array {
        $start = time() - 60;
        $end = time() + $expires;
        $signTime = sprintf('%d;%d', $start, $end);
        $hostHeaders = array_merge(['host' => $this->serviceHost()], $headers);
        $headerString = $this->canonicalList($hostHeaders);
        $headerList = implode(';', array_keys($this->normalizeMap($hostHeaders)));
        $queryString = $this->canonicalList($query);
        $queryList = implode(';', array_keys($this->normalizeMap($query)));
        $httpString = strtolower($method) . "\n/" . $this->encodeKey($key) . "\n" . $queryString . "\n" . $headerString . "\n";
        $signKey = hash_hmac('sha1', $signTime, (string)$this->config['secret_key'], false);
        $stringToSign = "sha1\n{$signTime}\n" . sha1($httpString) . "\n";
        $signature = hash_hmac('sha1', $stringToSign, $signKey);

        return array_merge($hostHeaders, [
            'Authorization' => sprintf(
                'q-sign-algorithm=sha1&q-ak=%s&q-sign-time=%s&q-key-time=%s&q-header-list=%s&q-url-param-list=%s&q-signature=%s',
                (string)$this->config['secret_id'],
                $signTime,
                $signTime,
                $headerList,
                $queryList,
                $signature
            ),
        ]);
    }

    /**
     * @param array<string, string> $map
     */
    private function canonicalList(array $map): string
    {
        $normalized = $this->normalizeMap($map);
        $items = [];
        foreach ($normalized as $key => $value) {
            $items[] = rawurlencode($key) . '=' . rawurlencode($value);
        }

        return implode('&', $items);
    }

    /**
     * @param array<string, string> $map
     * @return array<string, string>
     */
    private function normalizeMap(array $map): array
    {
        $normalized = [];
        foreach ($map as $key => $value) {
            $normalized[strtolower(trim((string)$key))] = trim((string)$value);
        }
        ksort($normalized);

        return $normalized;
    }

    /**
     * 对对象键逐段 URL 编码。
     */
    private function encodeKey(string $key): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $key)));
    }
}
