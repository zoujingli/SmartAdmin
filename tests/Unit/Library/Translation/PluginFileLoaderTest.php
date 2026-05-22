<?php

declare(strict_types=1);

namespace Tests\Unit\Library\Translation;

use Hyperf\Support\Filesystem\Filesystem;
use Library\Translation\PluginFileLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PluginFileLoader::class)]
final class PluginFileLoaderTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir() . '/xadmin-lang-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/base/zh_CN', 0777, true);
        mkdir($this->root . '/plugin-a/zh_CN', 0777, true);
        mkdir($this->root . '/plugin-b/zh_CN', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);

        parent::tearDown();
    }

    public function testLoadMergesPluginLanguagePathsAndAllowsBaseOverride(): void
    {
        file_put_contents($this->root . '/plugin-a/zh_CN/demo.php', '<?php return ["通用" => "插件A", "插件A" => "A"];');
        file_put_contents($this->root . '/plugin-b/zh_CN/demo.php', '<?php return ["插件B" => "B"];');
        file_put_contents($this->root . '/base/zh_CN/demo.php', '<?php return ["通用" => "项目覆盖"];');

        $loader = new PluginFileLoader(
            new Filesystem(),
            $this->root . '/base',
            [$this->root . '/plugin-a', $this->root . '/plugin-b']
        );

        $this->assertSame([
            '通用' => '项目覆盖',
            '插件A' => 'A',
            '插件B' => 'B',
        ], $loader->load('zh_CN', 'demo'));
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = $path . DIRECTORY_SEPARATOR . $item;
            is_dir($full) ? $this->removeDirectory($full) : unlink($full);
        }

        rmdir($path);
    }
}
