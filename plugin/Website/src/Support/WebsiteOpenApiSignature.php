<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Plugin\Website\Support;

/**
 * 官网开放接口签名工具。
 *
 * 签名串固定为 METHOD、PATH、规范化 Query、Body SHA256、Timestamp、Nonce 六段，避免 GET/POST、
 * 查询参数顺序和请求体编码差异造成第三方对接歧义。
 */
final class WebsiteOpenApiSignature
{
    /**
     * @param array<string, mixed> $query
     */
    public static function buildStringToSign(string $method, string $path, array $query, string $rawBody, string $timestamp, string $nonce): string
    {
        return strtoupper($method) . "\n"
            . self::normalizePath($path) . "\n"
            . self::canonicalQuery($query) . "\n"
            . hash('sha256', $rawBody) . "\n"
            . trim($timestamp) . "\n"
            . trim($nonce);
    }

    /**
     * @param array<string, mixed> $query
     */
    public static function sign(string $appKey, string $method, string $path, array $query, string $rawBody, string $timestamp, string $nonce): string
    {
        return hash_hmac('sha256', self::buildStringToSign($method, $path, $query, $rawBody, $timestamp, $nonce), $appKey);
    }

    /**
     * @param array<string, mixed> $query
     */
    public static function canonicalQuery(array $query): string
    {
        $query = self::sortRecursive($query);

        return http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    private static function normalizePath(string $path): string
    {
        $path = trim($path);

        return $path === '' ? '/' : $path;
    }

    /**
     * @param array<string, mixed> $value
     * @return array<string, mixed>
     */
    private static function sortRecursive(array $value): array
    {
        ksort($value);
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::sortRecursive($item);
            }
        }

        return $value;
    }
}
