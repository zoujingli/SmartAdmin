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
    public const AUTH_SCHEME = 'Website-HMAC';

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
     * 生成标准 Authorization 头，避免开放接口继续散落多个自定义 X-Website-* Header。
     */
    public static function authorizationHeader(string $appId, string $timestamp, string $nonce, string $signature): string
    {
        return sprintf(
            '%s appid="%s", timestamp="%s", nonce="%s", signature="%s"',
            self::AUTH_SCHEME,
            self::quoteAuthorizationValue($appId),
            self::quoteAuthorizationValue($timestamp),
            self::quoteAuthorizationValue($nonce),
            self::quoteAuthorizationValue(strtolower($signature))
        );
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

    private static function quoteAuthorizationValue(string $value): string
    {
        return addcslashes(trim($value), "\\\"");
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
