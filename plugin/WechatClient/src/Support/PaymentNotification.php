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

use Library\Exception\ErrorResponseException;
use We\Common\Provider\StaticTrustMaterialProvider;

/** 微信支付入站通知协议；不查询订单、不落库，验签和解密均失败关闭。 */
final class PaymentNotification
{
    public static function decrypt(array $headers, string $body, string $apiKey, string $serial, string $publicKey): array
    {
        $normalized = [];
        foreach ($headers as $name => $value) {
            $normalized[strtolower((string)$name)] = is_array($value) ? implode(',', $value) : (string)$value;
        }
        $timestamp = $normalized['wechatpay-timestamp'] ?? '';
        $nonce = $normalized['wechatpay-nonce'] ?? '';
        $signature = base64_decode($normalized['wechatpay-signature'] ?? '', true);
        $keyId = $normalized['wechatpay-serial'] ?? '';
        if ($nonce === '' || !is_string($signature) || $signature === '' || $serial === '' || !hash_equals($serial, $keyId)) {
            throw new ErrorResponseException('微信支付回调签名或平台序列号无效');
        }
        if (!ctype_digit($timestamp) || abs(time() - (int)$timestamp) > 300) {
            throw new ErrorResponseException('微信支付回调时间戳已过期或超出允许偏差');
        }
        // 必须使用原始 HTTP body 验签；禁止通过 JSON 重编码或 SDK 出站 Response 绕过入站验签。
        $trust = new StaticTrustMaterialProvider(['wechat.payment' => [$serial => $publicKey]]);
        $verified = openssl_verify($timestamp . "\n" . $nonce . "\n" . $body . "\n", $signature, $trust->publicKey('wechat.payment', $keyId), OPENSSL_ALGO_SHA256);
        if ($verified !== 1) {
            throw new ErrorResponseException('微信支付回调验签失败');
        }
        try {
            $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            $resource = is_array($payload) ? ($payload['resource'] ?? null) : null;
            if (!is_array($resource) || ($resource['algorithm'] ?? '') !== 'AEAD_AES_256_GCM' || strlen($apiKey) !== 32) {
                throw new ErrorResponseException('微信支付回调加密参数无效');
            }
            $cipher = is_string($resource['ciphertext'] ?? null) ? base64_decode($resource['ciphertext'], true) : false;
            $resourceNonce = $resource['nonce'] ?? null;
            $associatedData = $resource['associated_data'] ?? '';
            if (!is_string($cipher) || strlen($cipher) <= 16 || !is_string($resourceNonce) || strlen($resourceNonce) !== 12 || !is_string($associatedData)) {
                throw new ErrorResponseException('微信支付回调密文无效');
            }
            // GCM 密文末尾 16 字节为认证标签；标签、附加数据或密钥不匹配时不返回任何业务数据。
            $plain = openssl_decrypt(substr($cipher, 0, -16), 'AES-256-GCM', $apiKey, OPENSSL_RAW_DATA, $resourceNonce, substr($cipher, -16), $associatedData);
            if (!is_string($plain)) {
                throw new ErrorResponseException('微信支付回调解密失败');
            }
            $data = json_decode($plain, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($data)) {
                throw new ErrorResponseException('微信支付回调内容无效');
            }

            return $data;
        } catch (\JsonException) {
            throw new ErrorResponseException('微信支付回调 JSON 无效');
        }
    }
}
