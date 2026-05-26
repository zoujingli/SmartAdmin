<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Tests\Unit\WechatClient;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Plugin\WechatClient\Support\Secret;

/**
 * @internal
 */
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
