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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use System\Support\Storage\AlistStorage;
use System\Support\UploadDriver;

/**
 * @internal
 */
#[CoversClass(AlistStorage::class)]
final class AlistStorageTest extends TestCase
{
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
}
