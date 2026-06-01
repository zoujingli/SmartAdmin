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

use Library\Constants\MenuType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use System\Service\MenuService;

/**
 * @internal
 */
#[CoversClass(MenuService::class)]
final class MenuServiceTest extends TestCase
{
    public function testNormalizeUserMenuTreeKeepsExplicitDirectoryRedirect(): void
    {
        $menus = $this->normalizeUserMenuTree([
            [
                'id' => 9000,
                'type' => MenuType::PATH,
                'route' => '/system/material',
                'redirect' => '/system/material/price',
                'children' => [
                    [
                        'id' => 9001,
                        'type' => MenuType::MENU,
                        'route' => '/system/material/region',
                        'redirect' => '',
                    ],
                ],
            ],
        ]);

        $this->assertSame('/system/material/price', $menus[0]['redirect'] ?? null);
    }

    public function testNormalizeUserMenuTreeBackfillsMissingDirectoryRedirect(): void
    {
        $menus = $this->normalizeUserMenuTree([
            [
                'id' => 9000,
                'type' => MenuType::PATH,
                'route' => '/system/material',
                'redirect' => '',
                'children' => [
                    [
                        'id' => 9001,
                        'type' => MenuType::MENU,
                        'route' => '/system/material/region',
                        'redirect' => '',
                    ],
                ],
            ],
        ]);

        $this->assertSame('/system/material/region', $menus[0]['redirect'] ?? null);
    }

    /**
     * 菜单归一化是请求态树处理逻辑，测试直接反射私有方法，避免真实登录和数据库依赖。
     *
     * @param array<int, array<string, mixed>> $menus
     * @return array<int, array<string, mixed>>
     */
    private function normalizeUserMenuTree(array $menus): array
    {
        $reflection = new \ReflectionClass(MenuService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('normalizeUserMenuTree');
        $method->setAccessible(true);

        return $method->invoke($service, $menus);
    }
}
