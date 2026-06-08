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

use Library\Support\PluginManifestRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use System\Mapper\DataMapper;
use System\Mapper\UserMapper;
use System\Service\AuthCacheService;
use System\Service\DataService;
use System\Service\OnlineUserService;
use System\Service\UserService;

/**
 * @internal
 */
#[CoversClass(DataService::class)]
final class DataServiceModuleGuideTest extends TestCase
{
    public function testModuleGuideIsDisabledWhenNoEnabledGuideEntryExists(): void
    {
        $this->withPluginManifests([
            [
                'plugin' => 'Demo',
                'code' => 'demo',
                'guide_entry' => [
                    'name' => '演示入口',
                    'description' => '已关闭入口',
                    'icon' => 'lucide:blocks',
                    'home_path' => '/demo/home',
                    'login_path' => '/demo/login',
                    'sort' => 1,
                    'enabled' => false,
                ],
            ],
        ], function (): void {
            $result = $this->makeService([
                'module_guide_enable' => true,
                'app_name' => '测试后台',
            ])->getModuleGuide();

            self::assertFalse($result['enabled']);
            self::assertSame([], $result['entries']);
            self::assertSame('测试后台', $result['app']['name']);
        });
    }

    public function testModuleGuideIsEnabledWhenSwitchAndEntriesAreAvailable(): void
    {
        $this->withPluginManifests([
            [
                'plugin' => 'Demo',
                'code' => 'demo',
                'guide_entry' => [
                    'name' => '演示入口',
                    'description' => '可用入口',
                    'icon' => 'lucide:blocks',
                    'home_path' => '/demo/home',
                    'login_path' => '/demo/login',
                    'sort' => 1,
                    'enabled' => true,
                ],
            ],
        ], function (): void {
            $result = $this->makeService(['module_guide_enable' => true])->getModuleGuide();

            self::assertTrue($result['enabled']);
            self::assertSame('demo', $result['entries'][0]['code'] ?? null);
        });
    }

    /**
     * 构造仅覆盖模块引导读取链路的 DataService，避免单测依赖真实数据库配置表。
     *
     * @param array<string, mixed> $appMeta
     */
    private function makeService(array $appMeta): DataService
    {
        ModuleGuideDataConfigModel::$rows = [
            ['name' => 'config_app_meta', 'value' => $appMeta],
        ];

        $mapper = (new \ReflectionClass(DataMapper::class))->newInstanceWithoutConstructor();
        $property = new \ReflectionProperty(DataMapper::class, 'model');
        $property->setValue($mapper, ModuleGuideDataConfigModel::class);

        return new DataService(
            $mapper,
            (new \ReflectionClass(UserMapper::class))->newInstanceWithoutConstructor(),
            (new \ReflectionClass(OnlineUserService::class))->newInstanceWithoutConstructor(),
            (new \ReflectionClass(AuthCacheService::class))->newInstanceWithoutConstructor(),
            (new \ReflectionClass(UserService::class))->newInstanceWithoutConstructor(),
        );
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

final class ModuleGuideDataConfigModel
{
    /**
     * @var array<int, array{name:string,value:mixed}>
     */
    public static array $rows = [];

    public static function where(mixed ...$arguments): ModuleGuideDataConfigQuery
    {
        return new ModuleGuideDataConfigQuery(self::$rows);
    }
}

final class ModuleGuideDataConfigQuery
{
    /**
     * @param array<int, array{name:string,value:mixed}> $rows
     */
    public function __construct(private array $rows) {}

    public function where(mixed ...$arguments): self
    {
        return $this;
    }

    public function get(): ModuleGuideDataConfigCollection
    {
        return new ModuleGuideDataConfigCollection($this->rows);
    }
}

final class ModuleGuideDataConfigCollection
{
    /**
     * @param array<int|string, mixed> $rows
     */
    public function __construct(private array $rows) {}

    public function pluck(string $value, string $key): self
    {
        $items = [];
        foreach ($this->rows as $index => $row) {
            if (is_array($row)) {
                $items[(string)($row[$key] ?? $index)] = $row[$value] ?? null;
            }
        }

        return new self($items);
    }

    /**
     * @return array<int|string, mixed>
     */
    public function toArray(): array
    {
        return $this->rows;
    }
}
