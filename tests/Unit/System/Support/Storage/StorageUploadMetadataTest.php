<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Tests\Unit\System\Support\Storage;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use System\Support\Storage\AbstractRemoteStorage;
use System\Support\Storage\CosStorage;
use System\Support\Storage\OssStorage;
use System\Support\UploadDriver;

/**
 * @internal
 */
#[CoversClass(OssStorage::class)]
#[CoversClass(CosStorage::class)]
final class StorageUploadMetadataTest extends TestCase
{
    public function testOssRelayUploadWritesContentDispositionHeader(): void
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

        $history = [];
        $this->injectMockClient($storage, [
            new Response(200),
            new Response(200, [
                'Content-Length' => '1',
                'Content-Type' => 'image/png',
            ]),
        ], $history);

        $storage->set('ab/cdef.png', 'x', false, 'demo file.png', ['mime_type' => 'image/png']);

        $this->assertSame(
            'attachment; filename="demo_file.png"; filename*=UTF-8\'\'demo%20file.png',
            $history[0]['request']->getHeaderLine('Content-Disposition')
        );
    }

    public function testOssDirectUploadSignatureReturnsContentDispositionHeader(): void
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

        $signed = $storage->upload([
            'download_name' => 'demo file.png',
            'name' => 'ab/cdef.png',
            'mime_type' => 'image/png',
        ]);

        $this->assertSame(
            'attachment; filename="demo_file.png"; filename*=UTF-8\'\'demo%20file.png',
            $signed['headers']['Content-Disposition'] ?? null
        );
    }

    public function testOssMultipartInitiationWritesContentDispositionHeader(): void
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

        $history = [];
        $this->injectMockClient($storage, [
            new Response(200, [], '<InitiateMultipartUploadResult><UploadId>upload-id</UploadId></InitiateMultipartUploadResult>'),
        ], $history);

        $result = $storage->initiateMultipartUpload('ab/cdef.png', 'image/png', 'demo file.png');

        $this->assertSame('upload-id', $result['upload_id']);
        $this->assertSame(
            'attachment; filename="demo_file.png"; filename*=UTF-8\'\'demo%20file.png',
            $history[0]['request']->getHeaderLine('Content-Disposition')
        );
    }

    public function testCosRelayUploadWritesContentDispositionHeader(): void
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

        $history = [];
        $this->injectMockClient($storage, [
            new Response(200),
            new Response(200, [
                'Content-Length' => '1',
                'Content-Type' => 'image/png',
            ]),
        ], $history);

        $storage->set('ab/cdef.png', 'x', false, 'demo file.png', ['mime_type' => 'image/png']);

        $this->assertSame(
            'attachment; filename="demo_file.png"; filename*=UTF-8\'\'demo%20file.png',
            $history[0]['request']->getHeaderLine('content-disposition')
        );
    }

    public function testCosDirectUploadSignatureReturnsContentDispositionHeader(): void
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

        $signed = $storage->upload([
            'download_name' => 'demo file.png',
            'name' => 'ab/cdef.png',
            'mime_type' => 'image/png',
        ]);

        $this->assertSame(
            'attachment; filename="demo_file.png"; filename*=UTF-8\'\'demo%20file.png',
            $signed['headers']['content-disposition'] ?? null
        );
    }

    /**
     * @param list<Response> $responses
     * @param array<int, array<string, mixed>> $history
     */
    private function injectMockClient(AbstractRemoteStorage $storage, array $responses, array &$history): void
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $client = new Client([
            'handler' => $stack,
            'http_errors' => false,
            'timeout' => 30,
        ]);

        $property = new \ReflectionProperty(AbstractRemoteStorage::class, 'client');
        $property->setValue($storage, $client);
    }
}
