<?php

declare(strict_types=1);

namespace Tests\Unit\WechatService;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Plugin\WechatService\Service\WechatServiceAuthService;
use ReflectionClass;

#[CoversClass(WechatServiceAuthService::class)]
final class AuthServiceTest extends TestCase
{
    public function testResolveAccountTypeDetectsMiniProgram(): void
    {
        $this->assertSame('mini_program', $this->resolveAccountType([
            'service_type_info' => ['id' => 2],
            'MiniProgramInfo' => ['visit_status' => 0],
        ], 2));
    }

    public function testResolveAccountTypeDetectsSubscriptionAndOfficialAccount(): void
    {
        $this->assertSame('subscription', $this->resolveAccountType([], 0));
        $this->assertSame('official_account', $this->resolveAccountType([], 2));
    }

    /**
     * 授权账号类型识别不依赖数据库和微信接口；通过反射覆盖小程序与公众号的分支。
     *
     * @param array<string,mixed> $authorizerInfo
     */
    private function resolveAccountType(array $authorizerInfo, int $serviceType): string
    {
        $reflection = new ReflectionClass(WechatServiceAuthService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('resolveAccountType');
        $method->setAccessible(true);

        return (string)$method->invoke($service, $authorizerInfo, $serviceType);
    }
}
