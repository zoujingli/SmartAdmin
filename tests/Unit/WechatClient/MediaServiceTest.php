<?php

declare(strict_types=1);

namespace Tests\Unit\WechatClient;

use Library\Exception\ErrorResponseException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Plugin\WechatClient\Model\WechatClientMedia;
use Plugin\WechatClient\Service\WechatClientMediaService;
use ReflectionClass;

#[CoversClass(WechatClientMediaService::class)]
final class MediaServiceTest extends TestCase
{
    public function testResolveUploadPathRejectsRawLocalFilePath(): void
    {
        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('文件地址必须是可访问的 http(s) URL，服务器本地文件请使用本地文件 ID');

        $reflection = new ReflectionClass(WechatClientMediaService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('resolveUploadPath');
        $method->setAccessible(true);

        // 素材表单不能直接传服务器路径，否则上传权限会变相拥有本地文件读取能力；本地文件必须通过系统文件 ID 解析。
        $method->invoke($service, new WechatClientMedia([
            'file_id' => 0,
            'file_url' => '/etc/passwd',
        ]));
    }

    public function testAssertPublicRemoteUrlRejectsLocalhost(): void
    {
        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('文件地址不能指向本机或内网地址');

        $this->invokePrivate('assertPublicRemoteUrl', 'http://127.0.0.1/private.jpg');
    }

    public function testAssertPublicRemoteUrlRejectsPrivateNetwork(): void
    {
        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('文件地址不能指向本机或内网地址');

        $this->invokePrivate('assertPublicRemoteUrl', 'https://192.168.1.10/media.png');
    }

    public function testAssertPublicRemoteUrlRejectsIpv6Loopback(): void
    {
        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('文件地址不能指向本机或内网地址');

        $this->invokePrivate('assertPublicRemoteUrl', 'https://[::1]/media.png');
    }

    public function testAssertPublicRemoteUrlRejectsUnresolvableHost(): void
    {
        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('文件地址无法解析或不可访问');

        $this->invokePrivate('assertPublicRemoteUrl', 'https://wechat-admin.invalid/media.png');
    }

    public function testAssertPublicRemoteUrlAllowsPublicIp(): void
    {
        $this->expectNotToPerformAssertions();

        $this->invokePrivate('assertPublicRemoteUrl', 'https://8.8.8.8/media.png');
    }

    /**
     * @param non-empty-string $method
     */
    private function invokePrivate(string $method, mixed ...$args): mixed
    {
        $reflection = new ReflectionClass(WechatClientMediaService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $methodReflection = $reflection->getMethod($method);
        $methodReflection->setAccessible(true);

        return $methodReflection->invoke($service, ...$args);
    }
}
