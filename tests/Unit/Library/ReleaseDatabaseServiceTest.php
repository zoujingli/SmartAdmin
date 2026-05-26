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

use Library\Service\ReleaseDatabaseService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ReleaseDatabaseService::class)]
final class ReleaseDatabaseServiceTest extends TestCase
{
    public function testNormalizeTablesFiltersInvalidNamesAndDeduplicates(): void
    {
        $this->assertSame(
            ['system_menu', 'system_tenant'],
            ReleaseDatabaseService::normalizeTables([' system_menu ', 'SYSTEM_MENU', 'system_tenant', 'bad-name', ''])
        );
    }

    public function testIgnoreTablesWinOverBackupTables(): void
    {
        $this->assertSame(
            ['system_menu'],
            ReleaseDatabaseService::effectiveTables(
                ['system_menu', 'system_logs_action', 'system_logs_change', 'migrations'],
                ['migrations', 'system_logs_action', 'system_logs_change']
            )
        );
    }

    public function testReleaseConfigOnlyContainsBackupAndIgnoreTables(): void
    {
        $config = require dirname(__DIR__, 3) . '/config/autoload/release.php';

        $this->assertSame(['backup_tables', 'ignore_tables'], array_keys($config));
    }

    public function testReleaseBackupAndInstallPackagePathsAreSeparated(): void
    {
        $this->assertSame('storage/extra/release', ReleaseDatabaseService::INSTALL_DIR);
        $this->assertSame('runtime/backup', ReleaseDatabaseService::BACKUP_DIR);
        $this->assertSame('database.schema.gz', ReleaseDatabaseService::SCHEMA_FILENAME);
        $this->assertSame('database.data.gz', ReleaseDatabaseService::DATA_FILENAME);
        $this->assertSame('database.meta.json', ReleaseDatabaseService::META_FILENAME);
    }
}
