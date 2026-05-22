<?php

declare(strict_types=1);

namespace Tests\Unit\System\Support;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use System\Support\Storage\CosStorage;
use System\Support\Storage\OssStorage;
use System\Support\Storage\QiniuStorage;
use System\Support\UploadDriver;

#[CoversClass(UploadDriver::class)]
final class UploadDriverTest extends TestCase
{
    public function testNormalizeRuntimeConfigFallsBackToLocalWhenOssIsIncomplete(): void
    {
        $config = UploadDriver::defaultConfig();
        $config['active_mode'] = UploadDriver::DRIVER_OSS;
        $config['drivers']['oss']['enabled'] = true;
        $config['drivers']['oss']['region'] = 'cn-hangzhou';
        $config['drivers']['oss']['endpoint'] = 'oss-cn-hangzhou.aliyuncs.com';
        $config['drivers']['oss']['bucket'] = 'demo-bucket';
        $config['drivers']['oss']['domain'] = 'demo-bucket.oss-cn-hangzhou.aliyuncs.com';

        $normalized = UploadDriver::normalizeRuntimeConfig($config);

        $this->assertSame(UploadDriver::DRIVER_LOCAL, $normalized['active_mode']);
        $this->assertFalse($normalized['drivers']['oss']['enabled']);
        $this->assertTrue($normalized['drivers']['local']['enabled']);
    }

    public function testNormalizeRuntimeConfigKeepsConfiguredOssActive(): void
    {
        $config = UploadDriver::defaultConfig();
        $config['active_mode'] = UploadDriver::DRIVER_OSS;
        $config['drivers']['oss']['enabled'] = true;
        $config['drivers']['oss']['region'] = 'cn-hangzhou';
        $config['drivers']['oss']['endpoint'] = 'oss-cn-hangzhou.aliyuncs.com';
        $config['drivers']['oss']['bucket'] = 'demo-bucket';
        $config['drivers']['oss']['domain'] = 'demo-bucket.oss-cn-hangzhou.aliyuncs.com';
        $config['drivers']['oss']['access_id_encrypted'] = 'encoded-access-id';
        $config['drivers']['oss']['access_secret_encrypted'] = 'encoded-access-secret';

        $normalized = UploadDriver::normalizeRuntimeConfig($config);

        $this->assertSame(UploadDriver::DRIVER_OSS, $normalized['active_mode']);
        $this->assertTrue($normalized['drivers']['oss']['enabled']);
    }

    public function testExpandedCloudRegionListsAreAvailable(): void
    {
        $this->assertSame('oss-cn-hangzhou.aliyuncs.com', OssStorage::suggestedEndpoint('cn-hangzhou'));
        $this->assertContains('cn-east-2', array_column(QiniuStorage::region(), 'value'));
        $this->assertContains('eu-frankfurt', array_column(CosStorage::region(), 'value'));
    }

    public function testNormalizeAllowExtsRemovesSpacesAndDuplicates(): void
    {
        $this->assertSame('png,jpg,rar', UploadDriver::normalizeAllowExts(' png, JPG ,rar,png '));
    }

    public function testNormalizeRuntimeConfigKeepsConfiguredAlistActive(): void
    {
        $config = UploadDriver::defaultConfig();
        $config['active_mode'] = UploadDriver::DRIVER_ALIST;
        $config['drivers']['alist']['enabled'] = true;
        $config['drivers']['alist']['endpoint'] = 'alist.example.com';
        $config['drivers']['alist']['root'] = '/upload';
        $config['drivers']['alist']['username'] = 'admin';
        $config['drivers']['alist']['password_encrypted'] = 'encoded-password';

        $normalized = UploadDriver::normalizeRuntimeConfig($config);

        $this->assertSame(UploadDriver::DRIVER_ALIST, $normalized['active_mode']);
        $this->assertTrue($normalized['drivers']['alist']['enabled']);
        $this->assertFalse(UploadDriver::supportsDirectUpload(UploadDriver::DRIVER_ALIST));
    }
}
