<?php

declare(strict_types=1);

namespace Plugin\WechatService\Support;

use Library\Exception\ErrorResponseException;
use Library\Helper\CoderHelper;

/**
 * 开放平台 JSON-RPC 网关 Token 校验工具。
 *
 * Token 由客户端用 Base64Url(JSON) 传递，服务端只信任服务端保存的 client_secret
 * 重新计算签名；时间窗口固定为 5 分钟，避免被截获后长期重放。
 */
final class GatewayToken
{
    private const TTL_SECONDS = 300;

    /**
     * 解码并校验 Token 基础结构，不触碰数据库凭据。
     *
     * @return array{class:string,appid:string,key:string,time:int,nonce:string,sign:string}
     */
    public static function decode(string $token, ?int $now = null): array
    {
        try {
            $payload = json_decode(CoderHelper::desafe64($token), true);
        } catch (\Throwable) {
            $payload = null;
        }
        if (!is_array($payload)) {
            throw new ErrorResponseException('网关 Token 格式无效');
        }

        $result = [
            'class' => (string)($payload['class'] ?? ''),
            'appid' => (string)($payload['appid'] ?? ''),
            'key' => (string)($payload['key'] ?? ''),
            'time' => (int)($payload['time'] ?? 0),
            'nonce' => (string)($payload['nonce'] ?? ''),
            'sign' => (string)($payload['sign'] ?? ''),
        ];

        if ($result['key'] === '' || $result['time'] <= 0 || $result['nonce'] === '' || $result['appid'] === '' || $result['class'] === '' || $result['sign'] === '') {
            throw new ErrorResponseException('网关 Token 参数不完整');
        }

        // 时间戳只允许短窗口内有效；客户端和服务端轻微时钟漂移按绝对值窗口允许。
        if (abs(($now ?? time()) - $result['time']) > self::TTL_SECONDS) {
            throw new ErrorResponseException('网关 Token 已过期');
        }

        return $result;
    }

    /**
     * 使用服务端保存的密钥校验 HMAC 签名。
     *
     * @param array{class:string,appid:string,key:string,time:int,nonce:string,sign:string} $payload
     */
    public static function assertSignature(array $payload, string $clientSecret): void
    {
        $expected = self::sign(
            $payload['class'],
            $payload['appid'],
            $payload['time'],
            $payload['nonce'],
            $payload['key'],
            $clientSecret
        );

        if (!hash_equals($expected, $payload['sign'])) {
            throw new ErrorResponseException('网关 Token 签名无效');
        }
    }

    /**
     * 校验凭据白名单；空白名单表示该凭据不限制 AppID。
     *
     * @param array<int,string> $allowedAppids
     */
    public static function assertAllowedAppid(array $allowedAppids, string $appid): void
    {
        if ($allowedAppids !== [] && !in_array($appid, $allowedAppids, true)) {
            throw new ErrorResponseException('网关凭据无权调用该 AppID');
        }
    }

    /**
     * 网关只接受租户侧 WechatClient 生成的 Token，避免其他调用方复用同一签名协议混入内部代调用链路。
     */
    public static function assertClientClass(string $class): void
    {
        if ($class !== 'wechat-client') {
            throw new ErrorResponseException('网关 Token 调用方无效');
        }
    }

    public static function sign(string $class, string $appid, int $time, string $nonce, string $clientKey, string $clientSecret): string
    {
        return hash_hmac('sha256', implode('|', [$class, $appid, $time, $nonce, $clientKey]), $clientSecret);
    }
}
