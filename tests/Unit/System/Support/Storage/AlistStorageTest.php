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
use System\Support\Storage\AlistStorage;
use System\Support\UploadDriver;

/**
 * @internal
 */
#[CoversClass(AlistStorage::class)]
final class AlistStorageTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->setTokenCache([]);
    }

    public function testUrlBuildsWithConfiguredPublicPathAndDomain(): void
    {
        $storage = new AlistStorage([
            'domain' => 'files.example.com',
            'endpoint' => 'http://127.0.0.1:5244',
            'public_path' => '/d',
            'root' => '/upload',
            'username' => 'admin',
        ], [
            'link_type' => UploadDriver::LINK_TYPE_FULL_URL,
            'protocol' => UploadDriver::PROTOCOL_HTTPS,
        ]);

        $this->assertSame(
            'https://files.example.com/d/upload/ab/cdef.png',
            $storage->url('ab/cdef.png')
        );

        $this->assertSame(
            'https://files.example.com/d/upload/ab/cdef.png?attname=demo.png',
            $storage->url('ab/cdef.png', false, 'demo.png')
        );
    }

    public function testStoragePathReturnsAlistAbsoluteStoragePath(): void
    {
        $storage = new AlistStorage([
            'endpoint' => 'http://127.0.0.1:5244',
            'public_path' => '/d',
            'root' => '/upload',
            'username' => 'admin',
        ], [
            'link_type' => UploadDriver::LINK_TYPE_STORAGE_PATH,
            'protocol' => UploadDriver::PROTOCOL_HTTPS,
        ]);

        $this->assertSame('upload/ab/cdef.png', $storage->url('ab/cdef.png'));
        $this->assertSame('upload/ab/cdef.png', $storage->path('ab/cdef.png'));
    }

    public function testTokenUsesShortLivedCache(): void
    {
        $storage = $this->makeStorage();
        $this->setTokenCache([
            $this->cacheKey() => [
                'expires_at' => time() + 60,
                'token' => 'cached-token',
            ],
        ]);

        $this->assertSame('cached-token', $this->invokeToken($storage));
    }

    public function testExpiredTokenRefreshesFromAlist(): void
    {
        $storage = $this->makeStorage();
        $this->setTokenCache([
            $this->cacheKey() => [
                'expires_at' => time() - 1,
                'token' => 'expired-token',
            ],
        ]);

        $history = [];
        $this->injectMockClient($storage, [
            new Response(200, [], '{"code":200,"message":"success","data":{"token":"fresh-token"}}'),
        ], $history);

        $this->assertSame('fresh-token', $this->invokeToken($storage));
        $this->assertSame('/api/auth/login', $history[0]['request']->getUri()->getPath());
        $this->assertSame(
            ['password' => 'secret', 'username' => 'admin'],
            json_decode((string)$history[0]['request']->getBody(), true)
        );
    }

    private function makeStorage(): AlistStorage
    {
        return new AlistStorage([
            'endpoint' => 'http://127.0.0.1:5244',
            'password' => 'secret',
            'public_path' => '/d',
            'root' => '/upload',
            'username' => 'admin',
        ], [
            'link_type' => UploadDriver::LINK_TYPE_FULL_URL,
            'protocol' => UploadDriver::PROTOCOL_HTTPS,
        ]);
    }

    private function cacheKey(): string
    {
        return sha1('http://127.0.0.1:5244|admin|secret');
    }

    /**
     * @param array<string, array{token:string,expires_at:int}> $cache
     */
    private function setTokenCache(array $cache): void
    {
        $property = new \ReflectionProperty(AlistStorage::class, 'tokenCache');
        $property->setValue(null, $cache);
    }

    private function invokeToken(AlistStorage $storage): string
    {
        $method = new \ReflectionMethod(AlistStorage::class, 'token');
        return (string)$method->invoke($storage);
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
