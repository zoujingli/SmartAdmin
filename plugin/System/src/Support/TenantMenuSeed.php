<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://github.com/zoujingli/SmartAdmin/blob/master/readme.md
 */

namespace System\Support;

use Library\Constants\MenuType;
use Library\Constants\Status;

final class TenantMenuSeed
{
    public static function rows(int $userId, ?string $now = null): array
    {
        $now ??= date('Y-m-d H:i:s');

        return [
            self::menu($userId, $now, 210, 200, '租户管理', 'system.tenant.index', '/system/tenant', '@plugin/System/views/tenant/index.vue', 'lucide:building-2', 50),
            self::button($userId, $now, 2101, 210, '新增租户', 'system.tenant.create', 10),
            self::button($userId, $now, 2102, 210, '编辑租户', 'system.tenant.update', 20),
            self::button($userId, $now, 2103, 210, '删除租户', 'system.tenant.delete', 30),
            self::button($userId, $now, 2104, 210, '导出租户', 'system.tenant.export', 40),
            self::button($userId, $now, 2105, 210, '恢复租户', 'system.tenant.recovery', 50),
            self::button($userId, $now, 2106, 210, '彻底删除租户', 'system.tenant.real-delete', 60),
        ];
    }

    private static function menu(
        int $userId,
        string $now,
        int $id,
        int $pid,
        string $name,
        string $code,
        string $route,
        string $component,
        string $icon,
        int $sort,
        int $status = Status::ENABLED,
        string $redirect = '',
        int $hideInMenu = 0,
        int $hideInBreadcrumb = 0,
        int $hideInTab = 0,
        int $keepAlive = 0,
        int $affixTab = 0,
        string $type = MenuType::MENU,
        string $link = '',
        string $iframeSrc = '',
    ): array {
        return [
            'id' => $id,
            'pid' => $pid,
            'level' => '',
            'name' => $name,
            'code' => $code,
            'icon' => $icon,
            'type' => $type,
            'route' => $route,
            'component' => $component,
            'redirect' => $redirect,
            'link' => $link,
            'iframe_src' => $iframeSrc,
            'hide_in_menu' => $hideInMenu,
            'hide_in_breadcrumb' => $hideInBreadcrumb,
            'hide_in_tab' => $hideInTab,
            'keep_alive' => $keepAlive,
            'affix_tab' => $affixTab,
            'sort' => $sort,
            'status' => $status,
            'remark' => '',
            'created_by' => $userId,
            'updated_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ];
    }

    private static function button(
        int $userId,
        string $now,
        int $id,
        int $pid,
        string $name,
        string $code,
        int $sort,
        int $status = Status::ENABLED,
    ): array {
        $row = self::menu($userId, $now, $id, $pid, $name, $code, '', '', '', $sort, $status, hideInMenu: 1, type: MenuType::BUTTON);
        $row['remark'] = '按钮权限节点';

        return $row;
    }
}
