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

use Hyperf\Contract\ConfigInterface;
use Library\Exception\ErrorResponseException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use System\Contract\FilePathUploadStorageInterface;
use Swoole\Constant;
use System\Service\FileUploadService;
use System\Support\Storage\AlistStorage;
use System\Support\Storage\CosStorage;
use System\Support\Storage\LocalStorage;
use System\Support\Storage\OssStorage;
use System\Support\Storage\QiniuStorage;
use System\Support\UploadDriver;

/**
 * @internal
 */
#[CoversClass(FileUploadService::class)]
final class FileUploadServiceTest extends TestCase
{
    public function testRelayTransportHonorsRuntimeRequestBodyLimit(): void
    {
        $service = $this->makeServiceWithPackageMaxLength(10 * 1024 * 1024);
        $common = $this->commonConfig();

        $this->assertSame(
            UploadDriver::TRANSPORT_RELAY_SINGLE,
            $this->invokePrivate($service, 'resolveTransport', [UploadDriver::DRIVER_LOCAL, $common, 2 * 1024 * 1024, ''])
        );
        $this->assertSame(
            UploadDriver::TRANSPORT_RELAY_CHUNK,
            $this->invokePrivate($service, 'resolveTransport', [UploadDriver::DRIVER_LOCAL, $common, 12 * 1024 * 1024, ''])
        );
        $this->assertSame(
            UploadDriver::TRANSPORT_RELAY_CHUNK,
            $this->invokePrivate($service, 'resolveTransport', [UploadDriver::DRIVER_ALIST, $common, 12 * 1024 * 1024, ''])
        );
    }

    public function testRelayFallbackForDirectDriverStillUsesBackendChunkDecision(): void
    {
        $service = $this->makeServiceWithPackageMaxLength(10 * 1024 * 1024);
        $common = $this->commonConfig();

        $this->assertSame(
            UploadDriver::TRANSPORT_DIRECT_SINGLE,
            $this->invokePrivate($service, 'resolveTransport', [UploadDriver::DRIVER_OSS, $common, 2 * 1024 * 1024, ''])
        );
        $this->assertSame(
            UploadDriver::TRANSPORT_DIRECT_MULTIPART,
            $this->invokePrivate($service, 'resolveTransport', [UploadDriver::DRIVER_OSS, $common, 30 * 1024 * 1024, ''])
        );
        $this->assertSame(
            UploadDriver::TRANSPORT_RELAY_CHUNK,
            $this->invokePrivate($service, 'resolveTransport', [UploadDriver::DRIVER_OSS, $common, 12 * 1024 * 1024, 'relay'])
        );
        $this->assertSame(
            UploadDriver::TRANSPORT_RELAY_SINGLE,
            $this->invokePrivate($service, 'resolveTransport', [UploadDriver::DRIVER_OSS, $common, 2 * 1024 * 1024, 'relay'])
        );
    }

    public function testAllDriverTransportMatrixIsExplicit(): void
    {
        $service = $this->makeServiceWithPackageMaxLength(10 * 1024 * 1024);
        $common = $this->commonConfig();

        $cases = [
            'local small relay' => [UploadDriver::DRIVER_LOCAL, 2, '', UploadDriver::TRANSPORT_RELAY_SINGLE],
            'local large relay chunk' => [UploadDriver::DRIVER_LOCAL, 12, '', UploadDriver::TRANSPORT_RELAY_CHUNK],
            'alist small relay' => [UploadDriver::DRIVER_ALIST, 2, '', UploadDriver::TRANSPORT_RELAY_SINGLE],
            'alist large relay chunk' => [UploadDriver::DRIVER_ALIST, 12, '', UploadDriver::TRANSPORT_RELAY_CHUNK],
            'oss small direct single' => [UploadDriver::DRIVER_OSS, 2, '', UploadDriver::TRANSPORT_DIRECT_SINGLE],
            'oss large direct multipart' => [UploadDriver::DRIVER_OSS, 30, '', UploadDriver::TRANSPORT_DIRECT_MULTIPART],
            'oss fallback large relay chunk' => [UploadDriver::DRIVER_OSS, 12, 'relay', UploadDriver::TRANSPORT_RELAY_CHUNK],
            'cos small direct single' => [UploadDriver::DRIVER_COS, 2, '', UploadDriver::TRANSPORT_DIRECT_SINGLE],
            'cos large direct single' => [UploadDriver::DRIVER_COS, 30, '', UploadDriver::TRANSPORT_DIRECT_SINGLE],
            'cos fallback large relay chunk' => [UploadDriver::DRIVER_COS, 12, 'relay', UploadDriver::TRANSPORT_RELAY_CHUNK],
            'qiniu small direct single' => [UploadDriver::DRIVER_QINIU, 2, '', UploadDriver::TRANSPORT_DIRECT_SINGLE],
            'qiniu large direct single' => [UploadDriver::DRIVER_QINIU, 30, '', UploadDriver::TRANSPORT_DIRECT_SINGLE],
            'qiniu fallback large relay chunk' => [UploadDriver::DRIVER_QINIU, 12, 'relay', UploadDriver::TRANSPORT_RELAY_CHUNK],
        ];

        foreach ($cases as $label => [$driver, $sizeMb, $uploadType, $expected]) {
            $this->assertSame(
                $expected,
                $this->invokePrivate($service, 'resolveTransport', [$driver, $common, $sizeMb * 1024 * 1024, $uploadType]),
                $label
            );
        }
    }

    public function testUploadTypeContractIsExplicit(): void
    {
        $service = $this->makeServiceWithPackageMaxLength(10 * 1024 * 1024);
        $common = $this->commonConfig();

        $this->assertSame(
            UploadDriver::TRANSPORT_DIRECT_MULTIPART,
            $this->invokePrivate($service, 'resolveTransport', [UploadDriver::DRIVER_OSS, $common, 30 * 1024 * 1024, 'direct'])
        );
        $this->assertSame(
            UploadDriver::TRANSPORT_RELAY_CHUNK,
            $this->invokePrivate($service, 'resolveTransport', [UploadDriver::DRIVER_OSS, $common, 12 * 1024 * 1024, 'relay'])
        );

        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('上传方式无效');
        $this->invokePrivate($service, 'resolveTransport', [UploadDriver::DRIVER_OSS, $common, 2 * 1024 * 1024, 'invalid']);
    }

    public function testMultipartCompletePartsMustBeCompleteAndUnique(): void
    {
        $service = $this->makeServiceWithPackageMaxLength(10 * 1024 * 1024);

        $parts = $this->invokePrivate($service, 'normalizeMultipartCompleteParts', [[
            'parts' => [
                ['part_number' => 2, 'etag' => 'etag-2'],
                ['part_number' => 1, 'etag' => 'etag-1'],
            ],
        ], 2]);
        $this->assertSame([
            ['PartNumber' => 1, 'ETag' => 'etag-1'],
            ['PartNumber' => 2, 'ETag' => 'etag-2'],
        ], $parts);

        foreach ([
            'missing part' => [[
                'parts' => [
                    ['part_number' => 1, 'etag' => 'etag-1'],
                ],
            ], '分片完成数量与上传会话不一致'],
            'duplicate part' => [[
                'parts' => [
                    ['part_number' => 1, 'etag' => 'etag-1'],
                    ['part_number' => 1, 'etag' => 'etag-1-again'],
                ],
            ], '分片编号重复'],
            'empty etag' => [[
                'parts' => [
                    ['part_number' => 1, 'etag' => 'etag-1'],
                    ['part_number' => 2, 'etag' => ''],
                ],
            ], '分片 ETag 不能为空'],
            'out of range' => [[
                'parts' => [
                    ['part_number' => 1, 'etag' => 'etag-1'],
                    ['part_number' => 3, 'etag' => 'etag-3'],
                ],
            ], '分片编号超出范围'],
        ] as $case) {
            [$payload, $message] = $case;
            try {
                $this->invokePrivate($service, 'normalizeMultipartCompleteParts', [$payload, 2]);
                $this->fail(sprintf('Expected multipart validation failure: %s', $message));
            } catch (ErrorResponseException $exception) {
                $this->assertSame($message, $exception->getMessage());
            }
        }
    }

    public function testRelayChunkPartSizeDoesNotExceedRuntimeRequestBodyLimit(): void
    {
        $service = $this->makeServiceWithPackageMaxLength(10 * 1024 * 1024);
        $common = $this->commonConfig([
            'part_size_mb' => 20,
        ]);

        $this->assertSame(
            9 * 1024 * 1024,
            $this->invokePrivate($service, 'resolvePartSizeBytes', [UploadDriver::TRANSPORT_RELAY_CHUNK, $common])
        );
        $this->assertSame(
            20 * 1024 * 1024,
            $this->invokePrivate($service, 'resolvePartSizeBytes', [UploadDriver::TRANSPORT_DIRECT_MULTIPART, $common])
        );
    }

    public function testPreparePerformsFastUploadBeforeCreatingSession(): void
    {
        $source = (string)file_get_contents(dirname(__DIR__, 4) . '/plugin/System/src/Service/FileUploadService.php');

        $this->assertStringContainsString("throw new ErrorResponseException('上传文件哈希无效')", $source);
        $this->assertLessThan(
            strpos($source, '$transport = $this->resolveTransport('),
            strpos($source, '$instantAsset = $this->tryFastUpload(')
        );
        $this->assertStringNotContainsString('if ($nameType === UploadDriver::NAME_TYPE_HASH) {' . PHP_EOL . '            $instantAsset', $source);
    }

    public function testRelayChunkPersistsMergedFileByPathForEveryBuiltInStorage(): void
    {
        foreach ([LocalStorage::class, AlistStorage::class, OssStorage::class, CosStorage::class, QiniuStorage::class] as $storageClass) {
            $this->assertTrue(
                is_a($storageClass, FilePathUploadStorageInterface::class, true),
                $storageClass
            );
        }

        $source = (string)file_get_contents(dirname(__DIR__, 4) . '/plugin/System/src/Service/FileUploadService.php');
        $this->assertStringContainsString('$asset = $this->persistUploadedFile(', $source);
        $this->assertStringNotContainsString('file_get_contents($mergedPath)', $source);
        $this->assertStringContainsString("\$chunkIndex < (int)\$session['part_count'] && \$chunkSize > (int)\$session['part_size']", $source);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function commonConfig(array $overrides = []): array
    {
        return array_merge([
            'chunk_threshold_mb' => 20,
            'multipart_threshold_mb' => 20,
            'part_size_mb' => 5,
        ], $overrides);
    }

    private function makeServiceWithPackageMaxLength(int $packageMaxLength): FileUploadService
    {
        $service = (new \ReflectionClass(FileUploadService::class))->newInstanceWithoutConstructor();
        $property = new \ReflectionProperty(FileUploadService::class, 'runtimeConfig');
        $property->setValue($service, new class($packageMaxLength) implements ConfigInterface {
            public function __construct(private int $packageMaxLength) {}

            public function get($key, $default = null): mixed
            {
                return $key === 'server.settings.' . Constant::OPTION_PACKAGE_MAX_LENGTH
                    ? $this->packageMaxLength
                    : $default;
            }

            public function set($key, $value): void {}

            public function has($key): bool
            {
                return $key === 'server.settings.' . Constant::OPTION_PACKAGE_MAX_LENGTH;
            }
        });

        return $service;
    }

    /**
     * @param list<mixed> $arguments
     */
    private function invokePrivate(FileUploadService $service, string $methodName, array $arguments): mixed
    {
        $method = new \ReflectionMethod(FileUploadService::class, $methodName);
        return $method->invokeArgs($service, $arguments);
    }
}
