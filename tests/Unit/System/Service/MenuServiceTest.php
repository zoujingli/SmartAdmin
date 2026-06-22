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

    public function testFilterUserMenuTreeKeepsAncestorsForAuthorizedChildren(): void
    {
        $menus = $this->filterUserMenuTreeByPermissions([
            [
                'id' => 1,
                'name' => '系统管理',
                'code' => 'system.index',
                'children' => [
                    [
                        'id' => 2,
                        'name' => '用户管理',
                        'code' => 'system.user.index',
                        'children' => [],
                    ],
                    [
                        'id' => 3,
                        'name' => '角色管理',
                        'code' => 'system.role.index',
                        'children' => [],
                    ],
                ],
            ],
        ], ['system.user.index' => true]);

        self::assertSame('系统管理', $menus[0]['name']);
        self::assertCount(1, $menus[0]['children']);
        self::assertSame('用户管理', $menus[0]['children'][0]['name']);
    }

    public function testUnauthorizedAncestorPageIsDowngradedToDirectoryContainer(): void
    {
        $filtered = $this->filterUserMenuTreeByPermissions([
            [
                'id' => 1,
                'name' => '父级页面',
                'code' => 'system.parent.index',
                'type' => MenuType::MENU,
                'route' => '/system/parent',
                'component' => '@plugin/System/views/parent/index.vue',
                'redirect' => '/system/parent',
                'link' => 'https://example.com',
                'iframe_src' => 'https://example.com/frame',
                'children' => [
                    [
                        'id' => 2,
                        'name' => '授权子页',
                        'code' => 'system.parent.child',
                        'type' => MenuType::MENU,
                        'route' => '/system/parent/child',
                        'component' => '@plugin/System/views/parent/child.vue',
                        'children' => [],
                    ],
                ],
            ],
        ], ['system.parent.child' => true]);
        $menus = $this->normalizeUserMenuTree($filtered);

        // 父级没有自身权限时只能作为结构目录承载授权子页，不能把父级页面组件或外链下发成可访问路由。
        self::assertSame(MenuType::PATH, $menus[0]['type']);
        self::assertSame('', $menus[0]['code']);
        self::assertSame('', $menus[0]['component']);
        self::assertSame('', $menus[0]['link']);
        self::assertSame('', $menus[0]['iframe_src']);
        self::assertSame('/system/parent/child', $menus[0]['redirect']);
        self::assertSame('system.parent.child', $menus[0]['children'][0]['code']);
    }

    public function testEmptyCodeAncestorPageIsAlsoDowngradedToDirectoryContainer(): void
    {
        $filtered = $this->filterUserMenuTreeByPermissions([
            [
                'id' => 1,
                'name' => '空权限父级',
                'code' => '',
                'type' => MenuType::MENU,
                'route' => '/system/public-parent',
                'component' => '@plugin/System/views/public-parent/index.vue',
                'children' => [
                    [
                        'id' => 2,
                        'name' => '授权子页',
                        'code' => 'system.public-parent.child',
                        'type' => MenuType::MENU,
                        'route' => '/system/public-parent/child',
                        'component' => '@plugin/System/views/public-parent/child.vue',
                        'children' => [],
                    ],
                ],
            ],
        ], ['system.public-parent.child' => true]);
        $menus = $this->normalizeUserMenuTree($filtered);

        // 普通用户菜单查询中的空 code 祖先来自补齐路径，不应因空 code 被视作自身已授权页面。
        self::assertSame(MenuType::PATH, $menus[0]['type']);
        self::assertSame('', $menus[0]['component']);
        self::assertSame('/system/public-parent/child', $menus[0]['redirect']);
    }

    public function testNormalizeMenuPayloadAcceptsHideInMenuCamelCase(): void
    {
        $payload = $this->normalizeMenuPayload(['hideInMenu' => true]);

        self::assertSame(1, $payload['hide_in_menu']);
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

    /**
     * @param array<int, array<string, mixed>> $menus
     * @param array<string, bool> $permissions
     * @return array<int, array<string, mixed>>
     */
    private function filterUserMenuTreeByPermissions(array $menus, array $permissions): array
    {
        $reflection = new \ReflectionClass(MenuService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('filterUserMenuTreeByPermissions');
        $method->setAccessible(true);

        return $method->invoke($service, $menus, $permissions);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeMenuPayload(array $payload): array
    {
        $reflection = new \ReflectionClass(MenuService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('normalizeMenuPayload');
        $method->setAccessible(true);

        return $method->invoke($service, $payload);
    }
}
