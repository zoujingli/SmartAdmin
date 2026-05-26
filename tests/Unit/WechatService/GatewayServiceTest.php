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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Plugin\WechatService\Service\WechatServiceGatewayService;

/**
 * @internal
 */
#[CoversClass(WechatServiceGatewayService::class)]
final class GatewayServiceTest extends TestCase
{
    public function testNormalizeAllowedAppidsTrimsSplitsAndDeduplicates(): void
    {
        $this->assertSame(
            ['wx_a', 'wx_b', 'wx_c'],
            $this->normalize(" wx_a\nwx_b,wx_a，wx_c ")
        );
    }

    public function testNormalizeAllowedAppidsRejectsTooLongAppid(): void
    {
        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('允许 AppID 最多 64 位');

        $this->normalize(str_repeat('x', 65));
    }

    /**
     * @return array<int,string>
     */
    private function normalize(mixed $value): array
    {
        $reflection = new \ReflectionClass(WechatServiceGatewayService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('normalizeAllowedAppids');
        $method->setAccessible(true);

        /* @var array<int,string> $result */
        return $method->invoke($service, $value);
    }
}
