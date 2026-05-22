<?php

declare(strict_types=1);

namespace Tests\Unit\WechatClient;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Plugin\WechatClient\Support\Secret;

#[CoversClass(Secret::class)]
final class SecretTest extends TestCase
{
    public function testSecretEncryptDecryptAndMask(): void
    {
        $cipher = Secret::encrypt('wechat-secret');

        $this->assertNotSame('wechat-secret', $cipher);
        $this->assertSame('wechat-secret', Secret::decrypt($cipher));
        $this->assertSame('******', Secret::mask($cipher));
        $this->assertTrue(Secret::isMask('******'));
    }
}
