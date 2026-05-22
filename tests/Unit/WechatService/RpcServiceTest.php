<?php

declare(strict_types=1);

namespace Tests\Unit\WechatService;

use Library\Exception\ErrorResponseException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Plugin\WechatService\Service\WechatServiceRpcService;
use ReflectionClass;

#[CoversClass(WechatServiceRpcService::class)]
final class RpcServiceTest extends TestCase
{
    public function testNormalizeWechatApiPathAllowsRelativePath(): void
    {
        $this->assertSame('cgi-bin/menu/create', $this->normalize('/cgi-bin/menu/create'));
    }

    public function testNormalizeWechatApiPathRejectsAbsoluteUrl(): void
    {
        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('微信接口路径无效');

        $this->normalize('https://example.com/cgi-bin/menu/create');
    }

    public function testNormalizeWechatApiPathRejectsProtocolRelativeUrl(): void
    {
        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('微信接口路径无效');

        $this->normalize('//example.com/cgi-bin/menu/create');
    }

    public function testNormalizeWechatApiPathRejectsInlineQuery(): void
    {
        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('微信接口路径无效');

        $this->normalize('cgi-bin/menu/create?access_token=bad');
    }

    /**
     * JSON-RPC 网关路径白名单是安全边界，单测直接覆盖私有规范化逻辑，避免真实请求微信官方接口。
     */
    private function normalize(string $path): string
    {
        $reflection = new ReflectionClass(WechatServiceRpcService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('normalizeWechatApiPath');
        $method->setAccessible(true);

        return (string)$method->invoke($service, $path);
    }
}
