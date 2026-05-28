<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace System\Support\Storage;

use Library\Exception\ErrorResponseException;
use System\Contract\MultipartUploadStorageInterface;
use System\Support\UploadDriver;

final class OssStorage extends AbstractRemoteStorage implements MultipartUploadStorageInterface
{
    /**
     * @return array<int, array<string, string>>
     */
    public static function region(): array
    {
        return [
            ['label' => '华东 1（杭州）', 'value' => 'cn-hangzhou', 'suggested_endpoint' => 'oss-cn-hangzhou.aliyuncs.com'],
            ['label' => '华东 2（上海）', 'value' => 'cn-shanghai', 'suggested_endpoint' => 'oss-cn-shanghai.aliyuncs.com'],
            ['label' => '华北 1（青岛）', 'value' => 'cn-qingdao', 'suggested_endpoint' => 'oss-cn-qingdao.aliyuncs.com'],
            ['label' => '华北 2（北京）', 'value' => 'cn-beijing', 'suggested_endpoint' => 'oss-cn-beijing.aliyuncs.com'],
            ['label' => '华北 3（张家口）', 'value' => 'cn-zhangjiakou', 'suggested_endpoint' => 'oss-cn-zhangjiakou.aliyuncs.com'],
            ['label' => '华北 5（呼和浩特）', 'value' => 'cn-huhehaote', 'suggested_endpoint' => 'oss-cn-huhehaote.aliyuncs.com'],
            ['label' => '华北 6（乌兰察布）', 'value' => 'cn-wulanchabu', 'suggested_endpoint' => 'oss-cn-wulanchabu.aliyuncs.com'],
            ['label' => '华南 1（深圳）', 'value' => 'cn-shenzhen', 'suggested_endpoint' => 'oss-cn-shenzhen.aliyuncs.com'],
            ['label' => '华南 2（河源）', 'value' => 'cn-heyuan', 'suggested_endpoint' => 'oss-cn-heyuan.aliyuncs.com'],
            ['label' => '华南 3（广州）', 'value' => 'cn-guangzhou', 'suggested_endpoint' => 'oss-cn-guangzhou.aliyuncs.com'],
            ['label' => '西南 1（成都）', 'value' => 'cn-chengdu', 'suggested_endpoint' => 'oss-cn-chengdu.aliyuncs.com'],
            ['label' => '西北 2（中卫）', 'value' => 'cn-zhongwei', 'suggested_endpoint' => 'oss-cn-zhongwei.aliyuncs.com'],
            ['label' => '中国香港', 'value' => 'cn-hongkong', 'suggested_endpoint' => 'oss-cn-hongkong.aliyuncs.com'],
            ['label' => '日本（东京）', 'value' => 'ap-northeast-1', 'suggested_endpoint' => 'oss-ap-northeast-1.aliyuncs.com'],
            ['label' => '韩国（首尔）', 'value' => 'ap-northeast-2', 'suggested_endpoint' => 'oss-ap-northeast-2.aliyuncs.com'],
            ['label' => '新加坡', 'value' => 'ap-southeast-1', 'suggested_endpoint' => 'oss-ap-southeast-1.aliyuncs.com'],
            ['label' => '马来西亚（吉隆坡）', 'value' => 'ap-southeast-3', 'suggested_endpoint' => 'oss-ap-southeast-3.aliyuncs.com'],
            ['label' => '印度尼西亚（雅加达）', 'value' => 'ap-southeast-5', 'suggested_endpoint' => 'oss-ap-southeast-5.aliyuncs.com'],
            ['label' => '菲律宾（马尼拉）', 'value' => 'ap-southeast-6', 'suggested_endpoint' => 'oss-ap-southeast-6.aliyuncs.com'],
            ['label' => '泰国（曼谷）', 'value' => 'ap-southeast-7', 'suggested_endpoint' => 'oss-ap-southeast-7.aliyuncs.com'],
            ['label' => '德国（法兰克福）', 'value' => 'eu-central-1', 'suggested_endpoint' => 'oss-eu-central-1.aliyuncs.com'],
            ['label' => '英国（伦敦）', 'value' => 'eu-west-1', 'suggested_endpoint' => 'oss-eu-west-1.aliyuncs.com'],
            ['label' => '美国（硅谷）', 'value' => 'us-west-1', 'suggested_endpoint' => 'oss-us-west-1.aliyuncs.com'],
            ['label' => '美国（弗吉尼亚）', 'value' => 'us-east-1', 'suggested_endpoint' => 'oss-us-east-1.aliyuncs.com'],
            ['label' => '墨西哥', 'value' => 'na-south-1', 'suggested_endpoint' => 'oss-na-south-1.aliyuncs.com'],
            ['label' => '阿联酋（迪拜）', 'value' => 'me-east-1', 'suggested_endpoint' => 'oss-me-east-1.aliyuncs.com'],
        ];
    }

    public static function suggestedEndpoint(string $region): string
    {
        $region = strtolower(trim($region));
        foreach (self::region() as $item) {
            if (strtolower((string)($item['value'] ?? '')) !== $region) {
                continue;
            }

            return (string)($item['suggested_endpoint'] ?? '');
        }

        return '';
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function set(string $name, string $file, bool $safe = false, ?string $attname = null, array $options = []): array
    {
        $key = $this->normalizeKey($name);
        $mimeType = trim((string)($options['mime_type'] ?? 'application/octet-stream'));
        $headers = $this->authorize('PUT', $key, [], '', $mimeType);
        if ($attname !== null && trim($attname) !== '') {
            $headers['Content-Disposition'] = $this->buildAttachmentDisposition($attname);
        }
        $response = $this->request('PUT', $this->serviceUrl($key), [
            'headers' => $headers,
            'body' => $file,
        ]);
        $this->ensureSuccessful($response, [200]);

        return $this->info($key);
    }

    /**
     * 读取 OSS 对象内容。
     */
    public function get(string $name, bool $safe = false): string
    {
        $key = $this->normalizeKey($name);
        $response = $this->request('GET', $this->serviceUrl($key), [
            'headers' => $this->authorize('GET', $key),
        ]);
        if ($response->getStatusCode() === 404) {
            throw new ErrorResponseException('文件不存在');
        }

        $this->ensureSuccessful($response, [200]);
        return (string)$response->getBody();
    }

    /**
     * 删除 OSS 对象。
     */
    public function del(string $name, bool $safe = false): bool
    {
        $key = $this->normalizeKey($name);
        $response = $this->request('DELETE', $this->serviceUrl($key), [
            'headers' => $this->authorize('DELETE', $key),
        ]);

        return in_array($response->getStatusCode(), [204, 404], true);
    }

    /**
     * 判断 OSS 对象是否存在。
     */
    public function has(string $name, bool $safe = false): bool
    {
        $key = $this->normalizeKey($name);
        $response = $this->request('HEAD', $this->serviceUrl($key), [
            'headers' => $this->authorize('HEAD', $key),
        ]);

        return $response->getStatusCode() === 200;
    }

    /**
     * 生成 OSS 对象访问地址。
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

        $url = $this->protocolPrefix() . $this->publicHost() . '/' . $this->encodeKey($key);
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
            'headers' => $this->authorize('HEAD', $key),
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
        $expires = max(60, (int)($context['expires'] ?? 3600));
        $headers = [
            'Content-Type' => $mimeType,
        ];
        $downloadName = trim((string)($context['download_name'] ?? ''));
        if ($downloadName !== '') {
            $headers['Content-Disposition'] = $this->buildAttachmentDisposition($downloadName);
        }

        return [
            'supported' => true,
            'method' => 'PUT',
            'upload_url' => $this->presignedUrl('PUT', $key, [], $mimeType, $expires),
            'headers' => $headers,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function initiateMultipartUpload(string $name, string $mimeType, ?string $downloadName = null, int $expires = 3600): array
    {
        $key = $this->normalizeKey($name);
        $headers = $this->authorize('POST', $key, ['uploads' => ''], '', $mimeType);
        if ($downloadName !== null && trim($downloadName) !== '') {
            $headers['Content-Disposition'] = $this->buildAttachmentDisposition($downloadName);
        }
        $response = $this->request('POST', $this->serviceUrl($key, ['uploads' => '']), [
            'headers' => $headers,
        ]);
        $this->ensureSuccessful($response, [200]);

        $xml = simplexml_load_string((string)$response->getBody());
        $uploadId = trim((string)($xml->UploadId ?? ''));
        if ($uploadId === '') {
            throw new ErrorResponseException('初始化 OSS 分片上传失败');
        }

        return [
            'upload_id' => $uploadId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function signMultipartPart(string $name, string $uploadId, int $partNumber, int $expires = 3600): array
    {
        $key = $this->normalizeKey($name);
        return [
            'method' => 'PUT',
            'upload_url' => $this->presignedUrl('PUT', $key, [
                'partNumber' => (string)$partNumber,
                'uploadId' => $uploadId,
            ], '', $expires),
            'headers' => [],
        ];
    }

    /**
     * @param array<int, array{PartNumber:int,ETag:string}> $parts
     * @return array<string, mixed>
     */
    public function completeMultipartUpload(string $name, string $uploadId, array $parts): array
    {
        $key = $this->normalizeKey($name);
        $xml = new \SimpleXMLElement('<CompleteMultipartUpload/>');
        foreach ($parts as $part) {
            $partNode = $xml->addChild('Part');
            $partNode->addChild('PartNumber', (string)$part['PartNumber']);
            $partNode->addChild('ETag', $part['ETag']);
        }

        $body = (string)$xml->asXML();
        $response = $this->request('POST', $this->serviceUrl($key, ['uploadId' => $uploadId]), [
            'headers' => $this->authorize('POST', $key, ['uploadId' => $uploadId], '', 'application/xml'),
            'body' => $body,
        ]);
        $this->ensureSuccessful($response, [200]);

        return $this->info($key);
    }

    /**
     * 终止 OSS 分片上传任务。
     */
    public function abortMultipartUpload(string $name, string $uploadId): bool
    {
        $key = $this->normalizeKey($name);
        $response = $this->request('DELETE', $this->serviceUrl($key, ['uploadId' => $uploadId]), [
            'headers' => $this->authorize('DELETE', $key, ['uploadId' => $uploadId]),
        ]);

        return in_array($response->getStatusCode(), [204, 404], true);
    }

    /**
     * @param array<string, string> $query
     * @return array<string, string>
     */
    private function authorize(
        string $method,
        string $key,
        array $query = [],
        string $contentMd5 = '',
        string $contentType = ''
    ): array {
        $date = gmdate('D, d M Y H:i:s \G\M\T');
        $stringToSign = implode("\n", [
            strtoupper($method),
            $contentMd5,
            $contentType,
            $date,
            $this->canonicalizedResource($key, $query),
        ]);

        // OSS 签名串包含 Content-MD5 / Content-Type 时，请求头必须同步发送相同值；
        // 否则服务端会按实际请求头重新计算签名，导致中转上传或分片初始化返回 403。
        $headers = [
            'Date' => $date,
            'Authorization' => sprintf(
                'OSS %s:%s',
                (string)$this->config['access_id'],
                base64_encode(hash_hmac('sha1', $stringToSign, (string)$this->config['access_secret'], true))
            ),
        ];

        if ($contentMd5 !== '') {
            $headers['Content-MD5'] = $contentMd5;
        }
        if ($contentType !== '') {
            $headers['Content-Type'] = $contentType;
        }

        return $headers;
    }

    /**
     * @param array<string, string> $query
     */
    private function presignedUrl(string $method, string $key, array $query = [], string $contentType = '', int $expires = 3600): string
    {
        $expireAt = time() + $expires;
        $stringToSign = implode("\n", [
            strtoupper($method),
            '',
            $contentType,
            (string)$expireAt,
            $this->canonicalizedResource($key, $query),
        ]);

        $query['OSSAccessKeyId'] = (string)$this->config['access_id'];
        $query['Expires'] = (string)$expireAt;
        $query['Signature'] = base64_encode(hash_hmac('sha1', $stringToSign, (string)$this->config['access_secret'], true));

        return $this->serviceUrl($key, $query);
    }

    /**
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
     * 获取 OSS 服务访问 host。
     */
    private function serviceHost(): string
    {
        $endpoint = UploadDriver::normalizeDomain((string)($this->config['endpoint'] ?? ''));
        if ($endpoint === '') {
            $endpoint = UploadDriver::normalizeDomain(self::suggestedEndpoint((string)($this->config['region'] ?? '')));
        }
        return sprintf('%s.%s', (string)$this->config['bucket'], $endpoint);
    }

    /**
     * 获取 OSS 对外访问 host。
     */
    private function publicHost(): string
    {
        $domain = UploadDriver::normalizeDomain((string)($this->config['domain'] ?? ''));
        return $domain !== '' ? $domain : $this->serviceHost();
    }

    /**
     * @param array<string, string> $query
     */
    private function canonicalizedResource(string $key, array $query = []): string
    {
        $resource = '/' . (string)$this->config['bucket'] . '/' . $key;
        if ($query === []) {
            return $resource;
        }

        $segments = [];
        foreach (['uploads', 'partNumber', 'uploadId'] as $field) {
            if (!array_key_exists($field, $query)) {
                continue;
            }

            $value = (string)$query[$field];
            $segments[] = $value === '' ? $field : sprintf('%s=%s', $field, $value);
        }

        return $segments === [] ? $resource : $resource . '?' . implode('&', $segments);
    }

    /**
     * 对对象键逐段 URL 编码。
     */
    private function encodeKey(string $key): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $key)));
    }
}
