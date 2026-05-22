<?php

declare(strict_types=1);

namespace Plugin\WechatClient\Support;

use Library\Constants\MenuType;
use Library\Constants\Status;

/**
 * 微信公众号插件菜单种子。
 *
 * 安装或刷新插件时使用固定 ID 生成后台菜单和按钮权限，避免重复写入。
 */
final class WechatClientMenuSeed
{
    /**
     * 返回插件占用的菜单和按钮权限 ID。
     */
    public static function ids(): array
    {
        return array_map('intval', array_column(self::rows(0, '1970-01-01 00:00:00'), 'id'));
    }

    /**
     * 生成完整菜单和按钮权限行。
     */
    public static function rows(int $userId, ?string $now = null): array
    {
        $now ??= date('Y-m-d H:i:s');

        return [
            self::menu($userId, $now, 3100, 0, '微信公众号平台', '', '/wechat/client', 'BasicLayout', 'lucide:message-circle', 70, type: MenuType::PATH, redirect: '/wechat/client/account'),
            // 公众号后台能力按业务域再分一级，目录节点不绑定权限码，仅作为前端路由和菜单树分组。
            self::menu($userId, $now, 3101, 3100, '基础管理', '', '/wechat/client/basic', '', 'lucide:settings-2', 50, type: MenuType::PATH, redirect: '/wechat/client/account'),
            self::menu($userId, $now, 3102, 3100, '内容互动', '', '/wechat/client/content', '', 'lucide:messages-square', 40, type: MenuType::PATH, redirect: '/wechat/client/menu'),
            self::menu($userId, $now, 3103, 3100, '支付能力', '', '/wechat/client/payment', '', 'lucide:wallet-cards', 30, type: MenuType::PATH, redirect: '/wechat/client/payment/merchant'),
            self::menu($userId, $now, 3110, 3101, '接口账号', 'wechat.client.account.index', '/wechat/client/account', '@plugin/WechatClient/views/account/index.vue', 'lucide:settings-2', 50),
            self::button($userId, $now, 31101, 3110, '新增接口账号', 'wechat.client.account.create', 10),
            self::button($userId, $now, 31102, 3110, '编辑接口账号', 'wechat.client.account.update', 20),
            self::button($userId, $now, 31103, 3110, '删除接口账号', 'wechat.client.account.delete', 30),
            self::menu($userId, $now, 3120, 3101, '粉丝管理', 'wechat.client.user.index', '/wechat/client/user', '@plugin/WechatClient/views/user/index.vue', 'lucide:users', 40),
            self::button($userId, $now, 31201, 3120, '同步粉丝', 'wechat.client.user.sync', 10),
            self::menu($userId, $now, 3150, 3102, '素材管理', 'wechat.client.media.index', '/wechat/client/media', '@plugin/WechatClient/views/media/index.vue', 'lucide:images', 30),
            self::button($userId, $now, 31501, 3150, '保存素材', 'wechat.client.media.save', 10),
            self::button($userId, $now, 31502, 3150, '同步素材', 'wechat.client.media.sync', 20),
            self::button($userId, $now, 31503, 3150, '上传素材', 'wechat.client.media.upload', 30),
            self::button($userId, $now, 31504, 3150, '删除素材', 'wechat.client.media.delete', 40),
            self::menu($userId, $now, 3160, 3102, '文章管理', 'wechat.client.article.index', '/wechat/client/article', '@plugin/WechatClient/views/article/index.vue', 'lucide:newspaper', 20),
            self::button($userId, $now, 31601, 3160, '保存文章', 'wechat.client.article.save', 10),
            self::button($userId, $now, 31602, 3160, '上传草稿', 'wechat.client.article.upload-draft', 20),
            self::button($userId, $now, 31603, 3160, '发布文章', 'wechat.client.article.publish', 30),
            self::button($userId, $now, 31604, 3160, '查询发布状态', 'wechat.client.article.query', 40),
            self::button($userId, $now, 31605, 3160, '删除文章', 'wechat.client.article.delete', 50),
            self::menu($userId, $now, 3170, 3102, '自动回复', 'wechat.client.reply.index', '/wechat/client/reply', '@plugin/WechatClient/views/reply/index.vue', 'lucide:message-square-reply', 40),
            self::button($userId, $now, 31701, 3170, '保存自动回复', 'wechat.client.reply.save', 10),
            self::button($userId, $now, 31702, 3170, '删除自动回复', 'wechat.client.reply.delete', 20),
            self::menu($userId, $now, 3130, 3102, '菜单发布', 'wechat.client.menu.index', '/wechat/client/menu', '@plugin/WechatClient/views/menu/index.vue', 'lucide:list-tree', 50),
            self::button($userId, $now, 31301, 3130, '保存菜单', 'wechat.client.menu.save', 10),
            self::button($userId, $now, 31302, 3130, '发布菜单', 'wechat.client.menu.publish', 20),
            self::button($userId, $now, 31303, 3130, '删除菜单', 'wechat.client.menu.delete', 30),
            // 支付能力按商户、订单、退款拆成独立菜单，菜单 ID 固定用于安装同步和数据库增量更新。
            self::menu($userId, $now, 3140, 3103, '支付商户', 'wechat.client.payment.merchant.index', '/wechat/client/payment/merchant', '@plugin/WechatClient/views/payment/merchant/index.vue', 'lucide:badge-japanese-yen', 50),
            self::button($userId, $now, 31401, 3140, '保存支付商户', 'wechat.client.payment.merchant.save', 10),
            self::menu($userId, $now, 3141, 3103, '支付订单', 'wechat.client.payment.order.index', '/wechat/client/payment/order', '@plugin/WechatClient/views/payment/order/index.vue', 'lucide:receipt-text', 40),
            self::button($userId, $now, 31411, 3141, '查询支付订单', 'wechat.client.payment.order.query', 10),
            self::button($userId, $now, 31412, 3141, '发起退款', 'wechat.client.payment.refund.create', 20),
            self::menu($userId, $now, 3142, 3103, '退款记录', 'wechat.client.payment.refund.index', '/wechat/client/payment/refund', '@plugin/WechatClient/views/payment/refund/index.vue', 'lucide:rotate-ccw', 30),
            self::button($userId, $now, 31421, 3142, '查询退款订单', 'wechat.client.payment.refund.query', 10),
        ];
    }

    /**
     * 构造菜单记录的公共字段。
     */
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

    /**
     * 构造按钮权限节点。
     */
    private static function button(int $userId, string $now, int $id, int $pid, string $name, string $code, int $sort): array
    {
        $row = self::menu($userId, $now, $id, $pid, $name, $code, '', '', '', $sort, hideInMenu: 1, type: MenuType::BUTTON);
        $row['remark'] = '按钮权限节点';

        return $row;
    }
}
