<?php

declare(strict_types=1);

namespace Tests\Unit\System\Support\Storage;

use GuzzleHttp\Psr7\ServerRequest;
use Hyperf\Context\Context;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use System\Support\Storage\CosStorage;
use System\Support\Storage\LocalStorage;
use System\Support\Storage\OssStorage;
use System\Support\Storage\QiniuStorage;
use System\Support\UploadDriver;

#[CoversClass(LocalStorage::class)]
#[CoversClass(OssStorage::class)]
#[CoversClass(QiniuStorage::class)]
#[CoversClass(CosStorage::class)]
final class StorageDownloadUrlTest extends TestCase
{
    protected function tearDown(): void
    {
        Context::destroy(ServerRequestInterface::class);
    }

    public function testLocalDownloadUrlAppendsAttachmentName(): void
    {
        $storage = new LocalStorage([
            'storage_path' => 'upload',
            'domain' => 'files.example.com',
            'root' => sys_get_temp_dir(),
        ], [
            'link_type' => UploadDriver::LINK_TYPE_FULL_URL,
            'protocol' => UploadDriver::PROTOCOL_HTTPS,
        ]);

        $this->assertSame(
            'https://files.example.com/upload/ab/cdef.png?attname=demo.png',
            $storage->url('ab/cdef.png', false, 'demo.png')
        );
    }

    public function testLocalDownloadUrlFallsBackToRequestOrigin(): void
    {
        Context::set(
            ServerRequestInterface::class,
            new ServerRequest('GET', 'http://internal.local/system/file', [
                'Host' => 'internal.local:8080',
                'X-Host' => 'admin.example.com',
                'X-Scheme' => 'https',
                'X-Port' => '9443',
            ])
        );

        $storage = new LocalStorage([
            'storage_path' => 'upload',
            'domain' => '',
            'root' => sys_get_temp_dir(),
        ], [
            'link_type' => UploadDriver::LINK_TYPE_FULL_URL,
            'protocol' => UploadDriver::PROTOCOL_HTTPS,
        ]);

        $this->assertSame(
            'https://admin.example.com:9443/upload/ab/cdef.png',
            $storage->url('ab/cdef.png')
        );
    }

    public function testQiniuDownloadUrlUsesAttname(): void
    {
        $storage = new QiniuStorage([
            'domain' => 'cdn.example.com',
            'bucket' => 'demo',
            'region' => 'z0',
            'access_key' => 'ak',
            'secret_key' => 'sk',
        ], [
            'link_type' => UploadDriver::LINK_TYPE_FULL_URL,
            'protocol' => UploadDriver::PROTOCOL_HTTPS,
        ]);

        $this->assertSame(
            'https://cdn.example.com/ab/cdef.png?attname=demo%20file.png',
            $storage->url('ab/cdef.png', false, 'demo file.png')
        );
    }

    public function testOssDownloadUrlUsesContentDisposition(): void
    {
        $storage = new OssStorage([
            'domain' => 'bucket.oss-cn-shanghai.aliyuncs.com',
            'bucket' => 'bucket',
            'endpoint' => 'oss-cn-shanghai.aliyuncs.com',
            'access_id' => 'ak',
            'access_secret' => 'sk',
        ], [
            'link_type' => UploadDriver::LINK_TYPE_FULL_URL,
            'protocol' => UploadDriver::PROTOCOL_HTTPS,
        ]);

        $disposition = rawurlencode('attachment; filename="demo_file.png"; filename*=UTF-8\'\'demo%20file.png');
        $this->assertSame(
            'https://bucket.oss-cn-shanghai.aliyuncs.com/ab/cdef.png?response-content-disposition=' . $disposition,
            $storage->url('ab/cdef.png', false, 'demo file.png')
        );
    }

    public function testCosDownloadUrlUsesContentDisposition(): void
    {
        $storage = new CosStorage([
            'domain' => 'bucket.cos.ap-shanghai.myqcloud.com',
            'bucket' => 'bucket',
            'region' => 'ap-shanghai',
            'secret_id' => 'sid',
            'secret_key' => 'skey',
        ], [
            'link_type' => UploadDriver::LINK_TYPE_FULL_URL,
            'protocol' => UploadDriver::PROTOCOL_HTTPS,
        ]);

        $disposition = rawurlencode('attachment; filename="demo_file.png"; filename*=UTF-8\'\'demo%20file.png');
        $this->assertSame(
            'https://bucket.cos.ap-shanghai.myqcloud.com/ab/cdef.png?response-content-disposition=' . $disposition,
            $storage->url('ab/cdef.png', false, 'demo file.png')
        );
    }
}
