<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Plugin\WechatClient\Support;

use GuzzleHttp\Psr7\Utils;
use Library\Exception\ErrorResponseException;
use We\Common\MultipartPart;
use We\Common\Request;

/** 将插件现有数组调用合同转换为 SDK 请求，不透传代理、文件路径等 Guzzle 运行选项。 */
final class SdkRequest
{
    public static function make(string $uri, array $payload, string $method, array $options = []): Request
    {
        $method = strtoupper(trim($method) ?: 'POST');
        $unsupported = array_diff(array_keys($options), ['query', 'headers', 'multipart', 'timeout', 'anonymous']);
        if ($unsupported !== []) {
            throw new ErrorResponseException('微信 SDK 请求选项不支持');
        }
        $request = Request::create($method, $uri);
        $query = is_array($options['query'] ?? null) ? $options['query'] : [];
        $request = $request->query($method === 'GET' ? array_merge($payload, $query) : $query);
        if (isset($options['multipart'])) {
            // 素材上传沿用现有文件流；只包装为 PSR-7 流，不关闭调用方拥有的资源。
            if ($method === 'GET' || $payload !== [] || !is_array($options['multipart']) || $options['multipart'] === []) {
                throw new ErrorResponseException('微信素材上传参数无效');
            }
            $parts = [];
            foreach ($options['multipart'] as $part) {
                if (!is_array($part) || !isset($part['name'], $part['contents'])) {
                    throw new ErrorResponseException('微信素材上传参数无效');
                }
                $headers = is_array($part['headers'] ?? null) ? $part['headers'] : [];
                $mediaType = null;
                foreach ($headers as $name => $value) {
                    if (strcasecmp((string)$name, 'Content-Type') === 0) {
                        $mediaType = (string)$value;
                        unset($headers[$name]);
                    }
                }
                $parts[] = new MultipartPart(
                    (string)$part['name'],
                    is_resource($part['contents']) ? Utils::streamFor($part['contents']) : $part['contents'],
                    isset($part['filename']) ? (string)$part['filename'] : null,
                    $mediaType,
                    $headers,
                );
            }
            $request = $request->multipart(...$parts);
        } elseif ($method !== 'GET' && $payload !== []) {
            $request = $request->json($payload);
        }
        if (isset($options['headers'])) {
            $request = $request->headers($options['headers']);
        }
        if (isset($options['timeout'])) {
            // 既有 options.timeout 使用秒，新 SDK 接受毫秒；不改变调用方的时间单位。
            $request = $request->timeout((int)ceil((float)$options['timeout'] * 1000));
        }

        return ($options['anonymous'] ?? false) === true ? $request->anonymous() : $request;
    }
}
