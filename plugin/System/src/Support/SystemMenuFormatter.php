<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace System\Support;

use Library\Constants\MenuType;
use Library\Constants\Status;
use System\Model\SystemMenu;

final class SystemMenuFormatter
{
    public static function decorateModel(SystemMenu $menu): SystemMenu
    {
        $typeCode = MenuType::normalize($menu->type);
        $menu->type_code = $typeCode;
        $menu->type_value = MenuType::toFormValue($typeCode);
        $menu->type = MenuType::toFormValue($typeCode);
        $menu->path = $menu->route;
        $menu->permission = $menu->code;
        $menu->parentId = $menu->pid;
        $menu->createdAt = $menu->created_at;
        $menu->updatedAt = $menu->updated_at;

        return $menu;
    }

    /**
     * @param array<int, array<string, mixed>> $menus
     * @return array<int, array<string, mixed>>
     */
    public static function toAdminTree(array $menus): array
    {
        $result = [];

        foreach ($menus as $menu) {
            $result[] = self::toAdminItem($menu);
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $menus
     * @return array<int, array<string, mixed>>
     */
    public static function toFrontendTree(array $menus): array
    {
        $result = [];

        foreach ($menus as $menu) {
            $result[] = self::toFrontendItem($menu);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $menu
     * @return array<string, mixed>
     */
    private static function toAdminItem(array $menu): array
    {
        $typeCode = (string)($menu['type'] ?? MenuType::MENU);
        $menu['type_code'] = $typeCode;
        $menu['type'] = MenuType::toFormValue($typeCode);

        if (!empty($menu['children']) && is_array($menu['children'])) {
            $menu['children'] = self::toAdminTree($menu['children']);
        }

        return $menu;
    }

    /**
     * @param array<string, mixed> $menu
     * @return array<string, mixed>
     */
    private static function toFrontendItem(array $menu): array
    {
        $rawType = (string)($menu['type'] ?? MenuType::MENU);
        $item = [
            'id' => (int)($menu['id'] ?? 0),
            'parentId' => (int)($menu['pid'] ?? 0),
            // Vue Router 需唯一 name；展示标题用 meta.title（中文 name）
            'name' => self::resolveRouteUniqueName($menu),
            'path' => (string)($menu['route'] ?? ''),
            'route' => (string)($menu['route'] ?? ''),
            'component' => self::resolveComponentPath($menu),
            'icon' => (string)($menu['icon'] ?? ''),
            'type' => $rawType,
            'typeCode' => $rawType,
            'typeValue' => MenuType::toFormValue($rawType),
            'status' => (int)($menu['status'] ?? Status::ENABLED),
            'sort' => (int)($menu['sort'] ?? 0),
            'permission' => (string)($menu['code'] ?? ''),
            'code' => (string)($menu['code'] ?? ''),
            'redirect' => (string)($menu['redirect'] ?? ''),
            'link' => (string)($menu['link'] ?? ''),
            'iframeSrc' => (string)($menu['iframe_src'] ?? ''),
            'meta' => [
                'title' => (string)($menu['name'] ?? ''),
                'icon' => (string)($menu['icon'] ?? ''),
                'order' => 0 - (int)($menu['sort'] ?? 0),
                'hideInMenu' => (bool)($menu['hide_in_menu'] ?? false),
                'hideInBreadcrumb' => (bool)($menu['hide_in_breadcrumb'] ?? false),
                'hideInTab' => (bool)($menu['hide_in_tab'] ?? false),
                'keepAlive' => (bool)($menu['keep_alive'] ?? false),
                'affixTab' => (bool)($menu['affix_tab'] ?? false),
                'link' => (string)($menu['link'] ?? ''),
                'iframeSrc' => (string)($menu['iframe_src'] ?? ''),
                'typeCode' => $rawType,
                'typeValue' => MenuType::toFormValue($rawType),
            ],
        ];

        if (!empty($menu['children']) && is_array($menu['children'])) {
            $item['children'] = self::toFrontendTree($menu['children']);
        }

        return $item;
    }

    /**
     * 由 path 生成稳定、唯一的路由 name（与本地 modules 中 PascalCase 并存无碍）.
     *
     * @param array<string, mixed> $menu
     */
    private static function resolveRouteUniqueName(array $menu): string
    {
        $route = trim((string)($menu['route'] ?? ''));
        $route = trim($route, '/');
        if ($route !== '') {
            $slug = strtolower(str_replace(['/', '-', '.'], '_', $route));
            $slug = (string)preg_replace('/_+/', '_', $slug);
            $slug = trim($slug, '_');

            return $slug !== '' ? $slug : 'menu_' . (int)($menu['id'] ?? 0);
        }

        return 'menu_' . (int)($menu['id'] ?? 0);
    }

    /**
     * @param array<string, mixed> $menu
     */
    private static function resolveComponentPath(array $menu): string
    {
        $component = trim((string)($menu['component'] ?? ''));
        if ($component !== '') {
            return $component;
        }

        $type = MenuType::normalize($menu['type'] ?? MenuType::MENU);
        if ($type === MenuType::BUTTON) {
            return '';
        }

        if ($type === MenuType::PATH) {
            // 目录节点允许仅作为菜单和路由树分组存在；不自动推导组件，避免多级目录误加载不存在的 index.vue。
            return '';
        }

        $route = trim((string)($menu['route'] ?? ''));
        if ($route === '') {
            return '';
        }

        return ltrim($route, '/') . '/index.vue';
    }
}
