<?php

declare(strict_types=1);

namespace Plugin\WechatService\Support;

use Library\Constants\MenuType;
use Library\Constants\Status;

final class WechatServiceMenuSeed
{
    public static function ids(): array
    {
        return array_map('intval', array_column(self::rows(0, '1970-01-01 00:00:00'), 'id'));
    }

    public static function rows(int $userId, ?string $now = null): array
    {
        $now ??= date('Y-m-d H:i:s');

        return [
            self::menu($userId, $now, 3000, 0, '微信开放平台', '', '/wechat/service', 'BasicLayout', 'lucide:messages-square', 80, type: MenuType::PATH, redirect: '/wechat/service/config'),
            self::menu($userId, $now, 3010, 3000, '平台配置', 'wechat.service.config.index', '/wechat/service/config', '@plugin/WechatService/views/config/index.vue', 'lucide:settings', 30),
            self::button($userId, $now, 30101, 3010, '保存平台配置', 'wechat.service.config.save', 10),
            self::button($userId, $now, 30102, 3010, '生成授权链接', 'wechat.service.config.auth-url', 20),
            self::menu($userId, $now, 3020, 3000, '授权账号', 'wechat.service.auth.index', '/wechat/service/auth', '@plugin/WechatService/views/auth/index.vue', 'lucide:badge-check', 20),
            self::button($userId, $now, 30201, 3020, '同步授权账号', 'wechat.service.auth.sync', 10),
            self::button($userId, $now, 30202, 3020, '更新授权状态', 'wechat.service.auth.update', 20),
            self::button($userId, $now, 30203, 3020, '删除授权账号', 'wechat.service.auth.delete', 30),
            self::menu($userId, $now, 3030, 3000, '接口网关', 'wechat.service.gateway.index', '/wechat/service/gateway', '@plugin/WechatService/views/gateway/index.vue', 'lucide:key-round', 10),
            self::button($userId, $now, 30301, 3030, '保存网关凭据', 'wechat.service.gateway.save', 10),
            self::button($userId, $now, 30302, 3030, '删除网关凭据', 'wechat.service.gateway.delete', 20),
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
        string $type = MenuType::MENU,
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
            'link' => '',
            'iframe_src' => '',
            'hide_in_menu' => $hideInMenu,
            'hide_in_breadcrumb' => 0,
            'hide_in_tab' => 0,
            'keep_alive' => 0,
            'affix_tab' => 0,
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

    private static function button(int $userId, string $now, int $id, int $pid, string $name, string $code, int $sort): array
    {
        $row = self::menu($userId, $now, $id, $pid, $name, $code, '', '', '', $sort, hideInMenu: 1, type: MenuType::BUTTON);
        $row['remark'] = '按钮权限节点';

        return $row;
    }
}
