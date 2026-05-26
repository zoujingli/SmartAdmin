<?php

declare(strict_types=1);

namespace Tests\Unit\Library;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Library\Service\PluginManagerService;
use Library\Support\PluginManager\PluginArchive;
use Library\Support\PluginManager\PluginComposerManager;
use Library\Support\PluginManager\PluginMetadata;

/**
 * @internal
 */
#[CoversClass(PluginArchive::class)]
#[CoversClass(PluginComposerManager::class)]
#[CoversClass(PluginMetadata::class)]
#[CoversClass(PluginManagerService::class)]
final class PluginManagerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir() . '/xadmin-plugin-manager-' . bin2hex(random_bytes(4));
        mkdir($this->root, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
        parent::tearDown();
    }

    public function testMetadataUsesConsistentVersionAndExplicitTableRules(): void
    {
        $plugin = $this->makePlugin('DemoShop', [
            'composer_version' => '1.2.3',
            'plugin_version' => '1.2.3',
            'tables' => ['demo_order'],
            'table_prefixes' => ['demo_ext_'],
        ]);

        $metadata = PluginMetadata::load($plugin);

        self::assertSame('demo-shop', $metadata->code);
        self::assertSame('1.2.3', $metadata->version);
        self::assertSame('vendor/smart-plugin-demo-shop', $metadata->composerName);
        self::assertSame('DemoShop', $metadata->module);
        self::assertSame(['demo_order'], $metadata->tables);
        self::assertSame(['demo_ext_'], $metadata->tablePrefixes);
        self::assertSame(['demo.index', 'demo.order.index'], $metadata->menuCodes);
    }

    public function testMetadataFallsBackToCodeTablePrefixAndRejectsVersionDrift(): void
    {
        $plugin = $this->makePlugin('DemoOnlyPluginJson', [
            'composer_version' => '',
            'plugin_version' => '2.0.0',
        ]);

        $metadata = PluginMetadata::load($plugin);
        self::assertSame('2.0.0', $metadata->version);
        self::assertSame(['demo_only_plugin_json_'], $metadata->tablePrefixes);

        $drift = $this->makePlugin('DemoDrift', [
            'composer_version' => '1.0.0',
            'plugin_version' => '2.0.0',
        ]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('插件版本不一致');
        PluginMetadata::load($drift);
    }

    public function testEncryptedPackageRequiresPasswordAndExtractsSafely(): void
    {
        $plugin = $this->makePlugin('DemoZip', [
            'composer_version' => '1.0.0',
            'plugin_version' => '1.0.0',
        ]);
        $metadata = PluginMetadata::load($plugin);
        $archive = new PluginArchive($this->root . '/tmp');
        $zip = $archive->createPackage($metadata, $this->root . '/out', 'secret');

        self::assertSame('plugin-demo-zip-1.0.0.zip', basename($zip));

        $this->expectException(\RuntimeException::class);
        $archive->extract($zip);
    }

    public function testEncryptedPackageExtractsWithPassword(): void
    {
        $plugin = $this->makePlugin('DemoZipOk', [
            'composer_version' => '1.0.0',
            'plugin_version' => '1.0.0',
        ]);
        $metadata = PluginMetadata::load($plugin);
        $archive = new PluginArchive($this->root . '/tmp');
        $zip = $archive->createPackage($metadata, $this->root . '/out', 'secret');
        $extracted = $archive->extract($zip, 'secret');

        self::assertFileExists($extracted['root'] . '/composer.json');
        self::assertFileExists($extracted['root'] . '/plugin.json');
    }

    public function testArchiveRejectsZipTraversalPath(): void
    {
        $zipPath = $this->root . '/evil.zip';
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        $zip->addFromString('composer.json', '{}');
        $zip->addFromString('plugin.json', '{}');
        $zip->addFromString('../evil.php', 'bad');
        $zip->close();

        $archive = new PluginArchive($this->root . '/tmp');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('非法路径');
        $archive->extract($zipPath);
    }

    public function testCodeOnlyBackupContainsMetadataButNoDatabaseSnapshots(): void
    {
        $plugin = $this->makePlugin('DemoCodeBackup', [
            'composer_version' => '',
            'plugin_version' => '1.0.0',
        ]);
        $metadata = PluginMetadata::load($plugin);
        $archive = new PluginArchive($this->root . '/tmp');
        $zipPath = $archive->createBackup($metadata, $this->root . '/backups', null, null, [
            'format' => 'xadmin-plugin-backup',
            'version' => 1,
            'with_data' => false,
            'tables' => [],
            'rows' => 0,
        ]);

        self::assertMatchesRegularExpression('/demo-code-backup-1\\.0\\.0-backup-\\d{8}-\\d{6}\\.zip$/', $zipPath);
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($zipPath));
        self::assertNotFalse($zip->locateName('_xadmin/plugin-backup.json'));
        self::assertFalse($zip->locateName('_xadmin/database.schema.gz'));
        self::assertFalse($zip->locateName('_xadmin/database.data.gz'));
        $meta = json_decode((string)$zip->getFromName('_xadmin/plugin-backup.json'), true);
        $zip->close();

        self::assertIsArray($meta);
        self::assertFalse($meta['with_data']);
    }

    public function testDataBackupContainsDatabaseSnapshotsAndSupportsPassword(): void
    {
        $plugin = $this->makePlugin('DemoDataBackup', [
            'composer_version' => '',
            'plugin_version' => '1.0.0',
        ]);
        $schema = $this->root . '/database.schema.gz';
        $data = $this->root . '/database.data.gz';
        file_put_contents($schema, (string)gzencode('schema'));
        file_put_contents($data, (string)gzencode('data'));

        $metadata = PluginMetadata::load($plugin);
        $archive = new PluginArchive($this->root . '/tmp');
        $zipPath = $archive->createBackup($metadata, $this->root . '/backups', $schema, $data, [
            'format' => 'xadmin-plugin-backup',
            'version' => 1,
            'with_data' => true,
            'tables' => ['demo_data_backup_order'],
            'rows' => 3,
        ], 'secret');

        $this->expectException(\RuntimeException::class);
        $archive->extract($zipPath);
    }

    public function testDataBackupExtractsWithPasswordAndKeepsSnapshots(): void
    {
        $plugin = $this->makePlugin('DemoDataBackupOk', [
            'composer_version' => '',
            'plugin_version' => '1.0.0',
        ]);
        $schema = $this->root . '/database.schema.gz';
        $data = $this->root . '/database.data.gz';
        file_put_contents($schema, (string)gzencode('schema'));
        file_put_contents($data, (string)gzencode('data'));

        $metadata = PluginMetadata::load($plugin);
        $archive = new PluginArchive($this->root . '/tmp');
        $zipPath = $archive->createBackup($metadata, $this->root . '/backups', $schema, $data, [
            'format' => 'xadmin-plugin-backup',
            'version' => 1,
            'with_data' => true,
            'tables' => ['demo_data_backup_ok_order'],
            'rows' => 3,
        ], 'secret');
        $extracted = $archive->extract($zipPath, 'secret');

        self::assertIsArray($extracted['backup_meta']);
        self::assertTrue($extracted['backup_meta']['with_data']);
        self::assertFileExists($extracted['extract_dir'] . '/_xadmin/database.schema.gz');
        self::assertFileExists($extracted['extract_dir'] . '/_xadmin/database.data.gz');
    }

    public function testRestoreSourceUsesDefaultBackupDirectoryOnlyForPlainName(): void
    {
        $backupDir = $this->root . '/runtime/plugin/backups';
        mkdir($backupDir, 0777, true);
        file_put_contents($backupDir . '/demo-1.0.0-backup-20260522-123000.zip', 'zip');
        mkdir($this->root . '/custom', 0777, true);
        file_put_contents($this->root . '/custom/demo-backup', 'zip');

        $service = new PluginManagerService($this->root, $this->root);
        $method = new \ReflectionMethod(PluginManagerService::class, 'resolveZipSource');
        $method->setAccessible(true);

        self::assertSame(
            $backupDir . '/demo-1.0.0-backup-20260522-123000.zip',
            $method->invoke($service, 'demo-1.0.0-backup-20260522-123000', $backupDir, true)
        );
        self::assertSame(
            $this->root . '/custom/demo-backup',
            $method->invoke($service, 'custom/demo-backup', $backupDir, true)
        );
    }

    public function testComposerManagerAddsPathRepositoryVersionOptionAndRemovesIt(): void
    {
        file_put_contents($this->root . '/composer.json', json_encode([
            'require' => ['php' => '>=8.4'],
            'repositories' => [],
        ], JSON_PRETTY_PRINT));
        $plugin = $this->makePlugin('DemoComposer', [
            'composer_version' => '',
            'plugin_version' => '1.5.0',
        ]);
        $metadata = PluginMetadata::load($plugin);
        $manager = new PluginComposerManager($this->root);

        $manager->addPathPackage($metadata, 'plugin/DemoComposer');
        $rootComposer = json_decode((string)file_get_contents($this->root . '/composer.json'), true);

        self::assertSame('^1.5', $rootComposer['require']['vendor/smart-plugin-demo-composer']);
        self::assertSame('plugin/DemoComposer', $rootComposer['repositories'][0]['url']);
        self::assertSame('1.5.0', $rootComposer['repositories'][0]['options']['versions']['vendor/smart-plugin-demo-composer']);

        $manager->removePathPackage($metadata, 'plugin/DemoComposer');
        $rootComposer = json_decode((string)file_get_contents($this->root . '/composer.json'), true);
        self::assertArrayNotHasKey('vendor/smart-plugin-demo-composer', $rootComposer['require']);
        self::assertSame([], $rootComposer['repositories']);
    }

    /**
     * @param array{composer_version?:string,plugin_version?:string,tables?:array<int,string>,table_prefixes?:array<int,string>} $options
     */
    private function makePlugin(string $module, array $options): string
    {
        $path = $this->root . '/' . $module;
        mkdir($path . '/src', 0777, true);
        mkdir($path . '/stc/view/demo', 0777, true);
        file_put_contents($path . '/src/Provider.php', "<?php\ndeclare(strict_types=1);\nnamespace Plugin\\{$module};\nfinal class Provider{}\n");
        file_put_contents($path . '/stc/view/demo/index.vue', '<template><div /></template>');

        $composer = [
            'name' => 'vendor/smart-plugin-' . PluginMetadata::kebab($module),
            'type' => 'library',
            'autoload' => [
                'psr-4' => [
                    'Plugin\\' . $module . '\\' => 'src/',
                ],
            ],
            'extra' => [
                'hyperf' => [
                    'config' => 'Plugin\\' . $module . '\\Provider',
                ],
            ],
        ];
        if (($options['composer_version'] ?? '') !== '') {
            $composer['version'] = $options['composer_version'];
        }
        file_put_contents($path . '/composer.json', json_encode($composer, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        $manifest = [
            'plugin' => [
                'code' => PluginMetadata::kebab($module),
                'name' => $module,
                'version' => $options['plugin_version'] ?? '1.0.0',
                'view_root' => 'stc/view',
                'tables' => $options['tables'] ?? [],
                'table_prefixes' => $options['table_prefixes'] ?? [],
            ],
            'apps' => [[
                'id' => 990001,
                'name' => 'Demo',
                'code' => 'demo.index',
                'route' => '/demo',
                'type' => 'D',
                'menus' => [[
                    'id' => 990002,
                    'name' => 'DemoOrder',
                    'code' => 'demo.order.index',
                    'route' => '/demo/order',
                    'view' => 'demo/index.vue',
                ]],
            ]],
        ];
        if (($options['plugin_version'] ?? null) === '') {
            unset($manifest['plugin']['version']);
        }
        file_put_contents($path . '/plugin.json', json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        return $path;
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
        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            $item->isDir() && !$item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($path);
    }
}
