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
use Plugin\WechatService\Service\WechatServiceConfigService;

/**
 * @internal
 */
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
        $reflection = new \ReflectionClass(WechatServiceConfigService::class);

        /* @var WechatServiceConfigService $service */
        return $reflection->newInstanceWithoutConstructor();
    }
}
