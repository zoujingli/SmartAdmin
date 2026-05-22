<?php

declare(strict_types=1);

namespace Tests\Unit\WechatService;

use Library\Exception\ErrorResponseException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Plugin\WechatService\Service\WechatServiceConfigService;
use ReflectionClass;

#[CoversClass(WechatServiceConfigService::class)]
final class ConfigServiceTest extends TestCase
{
    public function testAuthorizationUrlRejectsInvalidRedirectUriBeforeWechatCall(): void
    {
        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('授权回调地址必须是完整 URL');

        $this->service()->authorizationUrl('/wechat-service/api/callback/auth');
    }

    public function testAuthorizationUrlRejectsInvalidAuthTypeBeforeWechatCall(): void
    {
        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('授权类型错误');

        $this->service()->authorizationUrl('https://example.com/wechat-service/api/callback/auth', 9);
    }

    private function service(): WechatServiceConfigService
    {
        $reflection = new ReflectionClass(WechatServiceConfigService::class);

        /** @var WechatServiceConfigService $service */
        $service = $reflection->newInstanceWithoutConstructor();

        return $service;
    }
}
