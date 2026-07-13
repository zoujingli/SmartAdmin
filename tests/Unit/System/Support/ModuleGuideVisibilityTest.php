<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Tests\Unit\System\Support;

use Library\Support\PluginManifestRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use System\Support\ModuleGuideVisibility;

/**
 * @internal
 */
#[CoversClass(ModuleGuideVisibility::class)]
final class ModuleGuideVisibilityTest extends TestCase
{
    public function testOptionsAndDefaultsOnlyIncludeManifestEnabledEntries(): void
    {
        $this->withPluginManifests($this->manifests(), function (): void {
            self::assertSame(['demo', 'system'], array_column(ModuleGuideVisibility::options(), 'code'));
            self::assertSame([
                'demo' => true,
                'system' => true,
            ], ModuleGuideVisibility::normalize([]));
            self::assertSame(['demo', 'system'], array_column(ModuleGuideVisibility::visibleEntries([]), 'code'));
        });
    }

    public function testRuntimeVisibilityRejectsUnknownAndManifestDisabledEntries(): void
    {
        $this->withPluginManifests($this->manifests(), function (): void {
            $visibility = ModuleGuideVisibility::normalize([
                'demo' => '0',
                'hidden' => true,
                'system' => false,
                'unknown' => true,
            ]);

            self::assertSame([
                'demo' => false,
                'system' => false,
            ], $visibility);
            self::assertSame([], ModuleGuideVisibility::visibleEntries($visibility));
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function manifests(): array
    {
        return [
            $this->manifest('Demo', 'demo', 20, true),
            $this->manifest('System', 'system', -100, true),
            $this->manifest('Hidden', 'hidden', 10, false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function manifest(string $plugin, string $code, int $sort, bool $enabled): array
    {
        return [
            'plugin' => $plugin,
            'code' => $code,
            'guide_entry' => [
                'name' => $plugin,
                'description' => $plugin . ' 入口',
                'icon' => 'lucide:blocks',
                'home_path' => '/' . $code . '/home',
                'login_path' => '/' . $code . '/login',
                'sort' => $sort,
                'enabled' => $enabled,
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $manifests
     */
    private function withPluginManifests(array $manifests, \Closure $callback): void
    {
        $property = new \ReflectionProperty(PluginManifestRegistry::class, 'manifests');
        $original = $property->getValue();
        $property->setValue(null, $manifests);

        try {
            $callback();
        } finally {
            $property->setValue(null, $original);
        }
    }
}
