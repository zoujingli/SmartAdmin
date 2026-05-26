<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Tests\Unit\System\Support;

use Library\Exception\ErrorResponseException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use System\Support\UploadConfigValidator;
use System\Support\UploadDriver;

/**
 * @internal
 */
#[CoversClass(UploadConfigValidator::class)]
final class UploadConfigValidatorTest extends TestCase
{
    public function testDefaultConfigCanPassValidation(): void
    {
        $validator = new UploadConfigValidator();

        $config = $validator->validate(UploadDriver::defaultConfig());

        $this->assertSame('local', $config['active_mode']);
        $this->assertTrue($config['drivers']['local']['enabled']);
        $this->assertSame('hash', $config['common']['name_type']);
    }

    public function testEnabledOssDriverRequiresEncryptedCredentials(): void
    {
        $validator = new UploadConfigValidator();
        $config = UploadDriver::defaultConfig();
        $config['active_mode'] = UploadDriver::DRIVER_OSS;
        $config['drivers']['oss']['enabled'] = true;
        $config['drivers']['oss']['region'] = 'cn-hangzhou';
        $config['drivers']['oss']['endpoint'] = 'oss-cn-hangzhou.aliyuncs.com';
        $config['drivers']['oss']['bucket'] = 'demo-bucket';
        $config['drivers']['oss']['domain'] = 'demo-bucket.oss-cn-hangzhou.aliyuncs.com';

        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('access_id');

        $validator->validate($config);
    }

    public function testEnabledOssNormalizesEndpointAndRequiresKnownRegion(): void
    {
        $validator = new UploadConfigValidator();
        $config = UploadDriver::defaultConfig();
        $config['active_mode'] = UploadDriver::DRIVER_OSS;
        $config['drivers']['oss']['enabled'] = true;
        $config['drivers']['oss']['region'] = 'CN-HANGZHOU';
        $config['drivers']['oss']['endpoint'] = 'https://oss-cn-hangzhou.aliyuncs.com/';
        $config['drivers']['oss']['bucket'] = 'demo-bucket';
        $config['drivers']['oss']['domain'] = 'https://demo-bucket.oss-cn-hangzhou.aliyuncs.com/';
        $config['drivers']['oss']['access_id_encrypted'] = 'encoded-access-id';
        $config['drivers']['oss']['access_secret_encrypted'] = 'encoded-access-secret';

        $validated = $validator->validate($config);

        $this->assertSame('cn-hangzhou', $validated['drivers']['oss']['region']);
        $this->assertSame('oss-cn-hangzhou.aliyuncs.com', $validated['drivers']['oss']['endpoint']);
        $this->assertSame('demo-bucket.oss-cn-hangzhou.aliyuncs.com', $validated['drivers']['oss']['domain']);
    }

    public function testMultipartThresholdCannotBeLessThanPartSize(): void
    {
        $validator = new UploadConfigValidator();
        $config = UploadDriver::defaultConfig();
        $config['drivers']['oss']['enabled'] = true;
        $config['drivers']['oss']['region'] = 'cn-hangzhou';
        $config['drivers']['oss']['endpoint'] = 'oss-cn-hangzhou.aliyuncs.com';
        $config['drivers']['oss']['bucket'] = 'demo-bucket';
        $config['drivers']['oss']['domain'] = 'demo-bucket.oss-cn-hangzhou.aliyuncs.com';
        $config['drivers']['oss']['access_id_encrypted'] = 'encoded-access-id';
        $config['drivers']['oss']['access_secret_encrypted'] = 'encoded-access-secret';
        $config['common']['multipart_threshold_mb'] = 5;
        $config['common']['part_size_mb'] = 10;

        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('分片阈值不能小于分片大小');

        $validator->validate($config);
    }

    public function testLocalStoragePathIsNormalizedToRelativePublicPath(): void
    {
        $validator = new UploadConfigValidator();
        $config = UploadDriver::defaultConfig();
        $config['drivers']['local']['storage_path'] = 'static/uploads/';
        $config['drivers']['local']['domain'] = 'https://files.example.com/';

        $validated = $validator->validate($config);

        $this->assertSame('static/uploads', $validated['drivers']['local']['storage_path']);
        $this->assertSame('files.example.com', $validated['drivers']['local']['domain']);
    }

    public function testLocalStoragePathCannotBeExternalUrl(): void
    {
        $validator = new UploadConfigValidator();
        $config = UploadDriver::defaultConfig();
        $config['drivers']['local']['storage_path'] = 'https://cdn.example.com/upload';

        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('相对 public 目录');

        $validator->validate($config);
    }

    public function testEnabledQiniuRequiresRegionBucketDomainAndSecrets(): void
    {
        $validator = new UploadConfigValidator();
        $config = UploadDriver::defaultConfig();
        $config['active_mode'] = UploadDriver::DRIVER_QINIU;
        $config['drivers']['qiniu']['enabled'] = true;
        $config['drivers']['qiniu']['region'] = 'z2';
        $config['drivers']['qiniu']['bucket'] = 'demo-bucket';
        $config['drivers']['qiniu']['domain'] = 'static.example.com';
        $config['drivers']['qiniu']['access_key_encrypted'] = 'encoded-access-key';
        $config['drivers']['qiniu']['secret_key_encrypted'] = 'encoded-secret-key';

        $validated = $validator->validate($config);

        $this->assertSame(UploadDriver::DRIVER_QINIU, $validated['active_mode']);
        $this->assertTrue($validated['drivers']['qiniu']['enabled']);
    }

    public function testEnabledAlistNormalizesEndpointAndPaths(): void
    {
        $validator = new UploadConfigValidator();
        $config = UploadDriver::defaultConfig();
        $config['active_mode'] = UploadDriver::DRIVER_ALIST;
        $config['drivers']['alist']['enabled'] = true;
        $config['drivers']['alist']['endpoint'] = 'alist.example.com/api';
        $config['drivers']['alist']['root'] = 'uploads/runtime/';
        $config['drivers']['alist']['public_path'] = 'd';
        $config['drivers']['alist']['username'] = 'admin';
        $config['drivers']['alist']['password_encrypted'] = 'encoded-password';

        $validated = $validator->validate($config);

        $this->assertSame(UploadDriver::DRIVER_ALIST, $validated['active_mode']);
        $this->assertSame('http://alist.example.com', $validated['drivers']['alist']['endpoint']);
        $this->assertSame('/uploads/runtime', $validated['drivers']['alist']['root']);
        $this->assertSame('/d', $validated['drivers']['alist']['public_path']);
    }
}
