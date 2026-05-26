<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Tests\Unit\WechatService;

use Library\Exception\ErrorResponseException;
use Library\Helper\CoderHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Plugin\WechatService\Support\GatewayToken;

/**
 * @internal
 */
#[CoversClass(GatewayToken::class)]
final class GatewayTokenTest extends TestCase
{
    public function testDecodeValidTokenAndAssertSignature(): void
    {
        $now = 1_762_000_000;
        $token = $this->makeToken('wechat-client', 'wx_appid', 'wsg_key', 'client-secret', $now, 'nonce-1');

        $payload = GatewayToken::decode($token, $now);

        $this->assertSame('wechat-client', $payload['class']);
        $this->assertSame('wx_appid', $payload['appid']);
        $this->assertSame('wsg_key', $payload['key']);
        GatewayToken::assertClientClass($payload['class']);
        GatewayToken::assertSignature($payload, 'client-secret');
        GatewayToken::assertAllowedAppid([], 'wx_appid');
        GatewayToken::assertAllowedAppid(['wx_appid'], 'wx_appid');
    }

    public function testDecodeRejectsExpiredToken(): void
    {
        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('网关 Token 已过期');

        GatewayToken::decode($this->makeToken(time: 1_762_000_000), 1_762_000_301);
    }

    public function testDecodeRejectsIncompleteToken(): void
    {
        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('网关 Token 参数不完整');

        GatewayToken::decode(CoderHelper::ensafe64(json_encode([
            'class' => 'wechat-client',
            'appid' => 'wx_appid',
            'key' => 'wsg_key',
            'time' => 1_762_000_000,
        ], JSON_UNESCAPED_SLASHES) ?: '{}'), 1_762_000_000);
    }

    public function testDecodeRejectsNonStandardClientKeyAlias(): void
    {
        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('网关 Token 参数不完整');

        // 网关 Token 只接受最新标准字段 key，旧字段名不能再作为有效凭据参与签名校验。
        GatewayToken::decode(CoderHelper::ensafe64(json_encode([
            'class' => 'wechat-client',
            'appid' => 'wx_appid',
            'client_key' => 'wsg_key',
            'time' => 1_762_000_000,
            'nonce' => 'nonce-1',
            'sign' => 'sign',
        ], JSON_UNESCAPED_SLASHES) ?: '{}'), 1_762_000_000);
    }

    public function testAssertSignatureRejectsInvalidSign(): void
    {
        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('网关 Token 签名无效');

        $payload = GatewayToken::decode($this->makeToken(sign: 'bad-sign'), 1_762_000_000);
        GatewayToken::assertSignature($payload, 'client-secret');
    }

    public function testAssertAllowedAppidRejectsDisallowedAppid(): void
    {
        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('网关凭据无权调用该 AppID');

        GatewayToken::assertAllowedAppid(['wx_other'], 'wx_appid');
    }

    public function testAssertClientClassRejectsInvalidCaller(): void
    {
        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('网关 Token 调用方无效');

        GatewayToken::assertClientClass('unknown-client');
    }

    private function makeToken(
        string $class = 'wechat-client',
        string $appid = 'wx_appid',
        string $clientKey = 'wsg_key',
        string $clientSecret = 'client-secret',
        int $time = 1_762_000_000,
        string $nonce = 'nonce-1',
        ?string $sign = null,
    ): string {
        // 测试 Token 与客户端真实协议保持一致：签名字段覆盖时用于模拟攻击或密钥错误。
        $payload = [
            'class' => $class,
            'appid' => $appid,
            'time' => $time,
            'nonce' => $nonce,
            'key' => $clientKey,
            'sign' => $sign ?? GatewayToken::sign($class, $appid, $time, $nonce, $clientKey, $clientSecret),
        ];

        return CoderHelper::ensafe64(json_encode($payload, JSON_UNESCAPED_SLASHES) ?: '{}');
    }
}
