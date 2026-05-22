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

final class SystemMenuSeed
{
    public static function ids(): array
    {
        return array_map('intval', array_column(self::rows(0, '1970-01-01 00:00:00'), 'id'));
    }

    public static function rows(int $userId, ?string $now = null): array
    {
        $now ??= date('Y-m-d H:i:s');

        return [
            self::menu($userId, $now, 1, 0, '仪表盘', '', '/dashboard', 'BasicLayout', 'lucide:layout-dashboard', 300, type: MenuType::PATH, redirect: '/dashboard/analytics'),
            self::menu($userId, $now, 11, 1, '分析页', 'dashboard.analytics', '/dashboard/analytics', '/dashboard/analytics/index', 'lucide:area-chart', 20, affixTab: 1),
            self::menu($userId, $now, 12, 1, '工作台', 'dashboard.workspace', '/dashboard/workspace', '/dashboard/workspace/index', 'carbon:workspace', 10),

            self::menu($userId, $now, 100, 0, '系统管理', '', '/system', 'BasicLayout', 'lucide:settings', 200, type: MenuType::PATH, redirect: '/system/user'),
            self::menu($userId, $now, 110, 100, '用户管理', 'system.user.index', '/system/user', '@plugin/System/views/user/index.vue', 'lucide:users', 50),
            self::button($userId, $now, 1101, 110, '新增用户', 'system.user.create', 10),
            self::button($userId, $now, 1102, 110, '编辑用户', 'system.user.update', 20),
            self::button($userId, $now, 1103, 110, '删除用户', 'system.user.delete', 30),
            self::button($userId, $now, 1104, 110, '重置密码', 'system.user.reset-password', 40),
            self::button($userId, $now, 1105, 110, '导出用户', 'system.user.export', 50),
            self::button($userId, $now, 1106, 110, '恢复用户', 'system.user.recovery', 60),
            self::button($userId, $now, 1107, 110, '彻底删除用户', 'system.user.real-delete', 70),

            self::menu($userId, $now, 120, 100, '角色管理', 'system.role.index', '/system/role', '@plugin/System/views/role/index.vue', 'lucide:shield', 40),
            self::button($userId, $now, 1201, 120, '新增角色', 'system.role.create', 10),
            self::button($userId, $now, 1202, 120, '编辑角色', 'system.role.update', 20),
            self::button($userId, $now, 1203, 120, '删除角色', 'system.role.delete', 30),
            self::button($userId, $now, 1204, 120, '分配权限', 'system.role.assign', 40),
            self::button($userId, $now, 1205, 120, '导出角色', 'system.role.export', 50),
            self::button($userId, $now, 1206, 120, '恢复角色', 'system.role.recovery', 60),
            self::button($userId, $now, 1207, 120, '彻底删除角色', 'system.role.real-delete', 70),

            self::menu($userId, $now, 130, 100, '菜单管理', 'system.menu.index', '/system/menu', '@plugin/System/views/menu/index.vue', 'lucide:menu', 30),
            self::button($userId, $now, 1301, 130, '新增菜单', 'system.menu.create', 10),
            self::button($userId, $now, 1302, 130, '编辑菜单', 'system.menu.update', 20),
            self::button($userId, $now, 1303, 130, '删除菜单', 'system.menu.delete', 30),
            self::button($userId, $now, 1304, 130, '导出菜单', 'system.menu.export', 40),
            self::button($userId, $now, 1305, 130, '恢复菜单', 'system.menu.recovery', 50),
            self::button($userId, $now, 1306, 130, '彻底删除菜单', 'system.menu.real-delete', 60),

            self::menu($userId, $now, 140, 100, '部门管理', 'system.dept.index', '/system/dept', '@plugin/System/views/dept/index.vue', 'lucide:building', 20),
            self::button($userId, $now, 1401, 140, '新增部门', 'system.dept.create', 10),
            self::button($userId, $now, 1402, 140, '编辑部门', 'system.dept.update', 20),
            self::button($userId, $now, 1403, 140, '删除部门', 'system.dept.delete', 30),
            self::button($userId, $now, 1404, 140, '导出部门', 'system.dept.export', 40),
            self::button($userId, $now, 1405, 140, '恢复部门', 'system.dept.recovery', 50),
            self::button($userId, $now, 1406, 140, '彻底删除部门', 'system.dept.real-delete', 60),

            self::menu($userId, $now, 170, 100, '岗位管理', 'system.post.index', '/system/post', '@plugin/System/views/post/index.vue', 'lucide:briefcase', 10),
            self::button($userId, $now, 1701, 170, '新增岗位', 'system.post.create', 10),
            self::button($userId, $now, 1702, 170, '编辑岗位', 'system.post.update', 20),
            self::button($userId, $now, 1703, 170, '删除岗位', 'system.post.delete', 30),
            self::button($userId, $now, 1704, 170, '导出岗位', 'system.post.export', 40),
            self::button($userId, $now, 1705, 170, '恢复岗位', 'system.post.recovery', 50),
            self::button($userId, $now, 1706, 170, '彻底删除岗位', 'system.post.real-delete', 60),

            self::menu($userId, $now, 175, 100, '数据字典', 'system.dict.index', '/system/dict', '@plugin/System/views/dict/index.vue', 'lucide:book-open-text', 5),
            self::button($userId, $now, 1751, 175, '新增字典', 'system.dict.create', 10),
            self::button($userId, $now, 1752, 175, '编辑字典', 'system.dict.update', 20),
            self::button($userId, $now, 1753, 175, '删除字典', 'system.dict.delete', 30),
            self::button($userId, $now, 1754, 175, '导出字典', 'system.dict.export', 40),
            self::button($userId, $now, 1755, 175, '恢复字典', 'system.dict.recovery', 50),
            self::button($userId, $now, 1756, 175, '彻底删除字典', 'system.dict.real-delete', 60),

            // System 模块自维护自身的运维菜单；租户菜单由独立种子追加，避免禁用租户入口后系统能力入口丢失。
            self::menu($userId, $now, 200, 0, '平台运维', '', '/system/ops', 'BasicLayout', 'lucide:blocks', 100, type: MenuType::PATH, redirect: '/system/logs/action'),
            self::menu($userId, $now, 220, 200, '通知中心', 'system.notice.index', '/system/notice', '@plugin/System/views/notice/index.vue', 'lucide:bell-ring', 40),
            self::button($userId, $now, 2201, 220, '新增公告', 'system.notice.create', 10),
            self::button($userId, $now, 2202, 220, '编辑公告', 'system.notice.update', 20),
            self::button($userId, $now, 2203, 220, '删除公告', 'system.notice.delete', 30),
            self::button($userId, $now, 2204, 220, '发布公告', 'system.notice.publish', 40),
            self::button($userId, $now, 2205, 220, '恢复公告', 'system.notice.recovery', 50),
            self::button($userId, $now, 2206, 220, '彻底删除公告', 'system.notice.real-delete', 60),
            self::button($userId, $now, 2207, 220, '更新公告状态', 'system.notice.status', 70),
            self::button($userId, $now, 2208, 220, '导出公告', 'system.notice.export', 80),
            self::menu($userId, $now, 150, 200, '操作日志', 'system.logs.action.index', '/system/logs/action', '@plugin/System/views/logs/action/index.vue', 'lucide:file-text', 30),
            self::button($userId, $now, 1501, 150, '删除日志', 'system.logs.action.delete', 10),
            self::button($userId, $now, 1502, 150, '清空日志', 'system.logs.action.clear', 20),
            self::button($userId, $now, 1503, 150, '导出日志', 'system.logs.action.export', 30),
            self::button($userId, $now, 1504, 150, '恢复日志', 'system.logs.action.recovery', 40),
            self::button($userId, $now, 1505, 150, '彻底删除日志', 'system.logs.action.real-delete', 50),
            self::menu($userId, $now, 155, 200, '变更日志', 'system.logs.change.index', '/system/logs/change', '@plugin/System/views/logs/change/index.vue', 'lucide:file-diff', 25),
            self::button($userId, $now, 1551, 155, '导出变更日志', 'system.logs.change.export', 10),
            self::menu($userId, $now, 160, 200, '文件管理', 'system.file.index', '/system/file', '@plugin/System/views/file/index.vue', 'lucide:folder-open', 20),
            self::button($userId, $now, 1601, 160, '上传文件', 'system.file.upload', 10),
            self::button($userId, $now, 1602, 160, '编辑文件', 'system.file.update', 20),
            self::button($userId, $now, 1603, 160, '删除文件', 'system.file.delete', 30),
            self::button($userId, $now, 1604, 160, '恢复文件', 'system.file.recovery', 40),
            self::button($userId, $now, 1605, 160, '彻底删除文件', 'system.file.real-delete', 50),
            self::button($userId, $now, 1606, 160, '维护上传通道配置', 'system.file.upload-config', 60),
            self::button($userId, $now, 1607, 160, '导出文件', 'system.file.export', 70),
            self::menu($userId, $now, 190, 200, '系统参数', 'system.setting.index', '/system/setting', '@plugin/System/views/setting/index.vue', 'lucide:sliders-horizontal', 15),
            self::button($userId, $now, 1901, 190, '保存系统参数', 'system.setting.save', 10),
            self::menu($userId, $now, 180, 200, '系统数据', 'system.data.index', '/system/data', '@plugin/System/views/data/index.vue', 'lucide:database', 10),
            self::button($userId, $now, 1801, 180, '保存配置', 'system.data.save', 10),
            self::button($userId, $now, 1802, 180, '清理缓存', 'system.data.clear-cache', 20),
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
