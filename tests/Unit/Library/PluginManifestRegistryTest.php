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

use Library\Support\PluginManifestRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PluginManifestRegistry::class)]
final class PluginManifestRegistryTest extends TestCase
{
    public function testSortMenuRowsUsesSystemMenuOrdering(): void
    {
        $rows = [
            ['id' => 110, 'pid' => 20, 'sort' => 10, 'name' => '子菜单-低排序'],
            ['id' => 15, 'pid' => 0, 'sort' => 20, 'name' => '根菜单-同排序大 ID'],
            ['id' => 100, 'pid' => 20, 'sort' => 30, 'name' => '子菜单-同排序大 ID'],
            ['id' => 20, 'pid' => 0, 'sort' => 80, 'name' => '根菜单-高排序'],
            ['id' => 90, 'pid' => 20, 'sort' => 30, 'name' => '子菜单-同排序小 ID'],
            ['id' => 10, 'pid' => 0, 'sort' => 20, 'name' => '根菜单-同排序小 ID'],
        ];

        $sorted = PluginManifestRegistry::sortMenuRows($rows);

        self::assertSame([20, 90, 100, 10, 15, 110], array_column($sorted, 'id'));
        self::assertSame([20, 10, 15], $this->idsByPid($sorted, 0));
        self::assertSame([90, 100, 110], $this->idsByPid($sorted, 20));
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, int>
     */
    private function idsByPid(array $rows, int $pid): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int)$row['id'],
            array_filter($rows, static fn (array $row): bool => (int)($row['pid'] ?? 0) === $pid)
        ));
    }
}
