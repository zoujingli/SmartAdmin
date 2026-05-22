<?php

declare(strict_types=1);

namespace Tests\Unit\WechatService;

use Library\Exception\ErrorResponseException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Plugin\WechatService\Support\BindState;

#[CoversClass(BindState::class)]
final class BindStateTest extends TestCase
{
    public function testMakeRejectsInvalidTenantId(): void
    {
        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('授权绑定租户无效');

        BindState::make(0);
    }

    public function testVerifyReturnsTenantId(): void
    {
        $state = BindState::make(1001);

        $this->assertSame(1001, BindState::verify($state));
    }
}
