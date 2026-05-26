<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Tests\Unit\System\Service;

use GuzzleHttp\Psr7\ServerRequest;
use Hyperf\Context\Context;
use Hyperf\Contract\ConfigInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use System\Service\UploadConfigService;
use System\Support\UploadConfigValidator;
use System\Support\UploadDriver;

/**
 * @internal
 */
#[CoversClass(UploadConfigService::class)]
final class UploadConfigServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Context::destroy(ServerRequestInterface::class);
    }

    public function testLocalFullUrlUsesConfiguredDomain(): void
    {
        $service = $this->makeService();

        $url = $service->buildPublicUrlFromConfig(
            UploadDriver::DRIVER_LOCAL,
            ['link_type' => UploadDriver::LINK_TYPE_FULL_URL, 'protocol' => UploadDriver::PROTOCOL_HTTPS],
            ['storage_path' => 'upload', 'domain' => 'files.example.com'],
            'ab',
            'cdef.png'
        );

        $this->assertSame('https://files.example.com/upload/ab/cdef.png', $url);
    }

    public function testLocalFullUrlFallsBackToCurrentRequestOrigin(): void
    {
        $service = $this->makeService();
        Context::set(
            ServerRequestInterface::class,
            new ServerRequest('GET', 'http://internal.local/system/file', [
                'Host' => 'internal.local:8080',
                'X-Host' => 'admin.example.com',
                'X-Scheme' => 'https',
                'X-Port' => '9443',
            ])
        );

        $url = $service->buildPublicUrlFromConfig(
            UploadDriver::DRIVER_LOCAL,
            ['link_type' => UploadDriver::LINK_TYPE_FULL_URL, 'protocol' => UploadDriver::PROTOCOL_HTTPS],
            ['storage_path' => 'upload', 'domain' => ''],
            'ab',
            'cdef.png'
        );

        $this->assertSame('https://admin.example.com:9443/upload/ab/cdef.png', $url);
    }

    public function testAlistFullUrlFallsBackToEndpointOrigin(): void
    {
        $service = $this->makeService();

        $url = $service->buildPublicUrlFromConfig(
            UploadDriver::DRIVER_ALIST,
            ['link_type' => UploadDriver::LINK_TYPE_FULL_URL, 'protocol' => UploadDriver::PROTOCOL_HTTPS],
            ['domain' => '', 'endpoint' => 'http://alist.example.com:5244/api', 'public_path' => '/d', 'root' => '/upload'],
            'ab',
            'cdef.png'
        );

        $this->assertSame('http://alist.example.com:5244/d/upload/ab/cdef.png', $url);
    }

    #[DataProvider('remoteDriverProvider')]
    public function testRemoteFullUrlUsesDriverDomain(string $driver): void
    {
        $service = $this->makeService();

        $url = $service->buildPublicUrlFromConfig(
            $driver,
            ['link_type' => UploadDriver::LINK_TYPE_FULL_URL, 'protocol' => UploadDriver::PROTOCOL_HTTPS],
            ['domain' => 'cdn.example.com'],
            'ab',
            'cdef.png'
        );

        $this->assertSame('https://cdn.example.com/ab/cdef.png', $url);
    }

    /**
     * @return array<string, array{0:string}>
     */
    public static function remoteDriverProvider(): array
    {
        return [
            'oss' => [UploadDriver::DRIVER_OSS],
            'qiniu' => [UploadDriver::DRIVER_QINIU],
            'cos' => [UploadDriver::DRIVER_COS],
        ];
    }

    private function makeService(): UploadConfigService
    {
        return new UploadConfigService(
            $this->createStub(ConfigInterface::class),
            new UploadConfigValidator(),
        );
    }
}
