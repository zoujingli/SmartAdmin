<?php

declare(strict_types=1);

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

    public function testReleaseSnapshotFilesLiveInRuntimePath(): void
    {
        $this->assertSame('runtime/release/database.schema.gz', ReleaseDatabaseService::SCHEMA_FILE);
        $this->assertSame('runtime/release/database.data.gz', ReleaseDatabaseService::DATA_FILE);
    }
}
