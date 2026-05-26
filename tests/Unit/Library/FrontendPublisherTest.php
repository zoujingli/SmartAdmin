<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Tests\Unit\Library;

use Library\Support\FrontendPublisher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(FrontendPublisher::class)]
final class FrontendPublisherTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir() . '/xadmin-frontend-publisher-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/web-dist/js', 0777, true);
        mkdir($this->root . '/public', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);

        parent::tearDown();
    }

    public function testPublishFromDirectorySkipsDynamicConfigAndWritesManifest(): void
    {
        file_put_contents($this->root . '/web-dist/index.html', '<div id="app"></div>');
        file_put_contents($this->root . '/web-dist/_app.config.js', 'window.appConfig = {};');
        file_put_contents($this->root . '/web-dist/js/app.js', 'console.log("ok");');

        $messages = [];
        $count = FrontendPublisher::publish(false, static function (string $message) use (&$messages): void {
            $messages[] = $message;
        }, $this->root . '/public', $this->root . '/web-dist');

        self::assertSame(2, $count);
        self::assertFileExists($this->root . '/public/index.html');
        self::assertFileExists($this->root . '/public/js/app.js');
        self::assertFileDoesNotExist($this->root . '/public/_app.config.js');
        self::assertFileExists($this->root . '/runtime/site-publish-manifest.json');
        self::assertTrue(FrontendPublisher::publicReady($this->root . '/public'));
        self::assertContains('copy  index.html', $messages);
    }

    public function testPublishFromZipRejectsUnsafeRelativePath(): void
    {
        $zipFile = $this->root . '/web-dist.zip';
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        $zip->addFromString('index.html', '<div id="app"></div>');
        $zip->addFromString('../evil.php', '<?php echo "bad";');
        $zip->close();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('非法路径');

        FrontendPublisher::publish(false, null, $this->root . '/public', $zipFile);
    }

    public function testCleanRemovesPublishedFilesButKeepsDynamicConfig(): void
    {
        file_put_contents($this->root . '/web-dist/index.html', '<div id="app"></div>');
        file_put_contents($this->root . '/web-dist/_app.config.js', 'window.appConfig = {};');
        file_put_contents($this->root . '/web-dist/js/app.js', 'console.log("ok");');
        file_put_contents($this->root . '/public/_app.config.js', 'window.runtime = {};');

        FrontendPublisher::publish(false, null, $this->root . '/public', $this->root . '/web-dist');
        $count = FrontendPublisher::clean(false, null, $this->root . '/public');

        self::assertGreaterThanOrEqual(2, $count);
        self::assertFileDoesNotExist($this->root . '/public/index.html');
        self::assertFileDoesNotExist($this->root . '/public/js/app.js');
        self::assertFileExists($this->root . '/public/_app.config.js');
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $fileInfo) {
            /* @var \SplFileInfo $fileInfo */
            $fileInfo->isDir() && !$fileInfo->isLink()
                ? @rmdir($fileInfo->getPathname())
                : @unlink($fileInfo->getPathname());
        }
        @rmdir($path);
    }
}
