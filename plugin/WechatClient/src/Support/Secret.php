<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Plugin\WechatClient\Support;

use Library\Helper\CoderHelper;

use function Hyperf\Config\config;

/**
 * 插件敏感字段加解密工具。
 *
 * 当前用于微信支付商户密钥、证书和平台公钥字段，避免明文保存到数据库。
 */
final class Secret
{
    /**
     * 加密非空字符串；空值保持为空，便于可选配置保存。
     */
    public static function encrypt(?string $value): string
    {
        $value = trim((string)$value);

        return $value === '' ? '' : CoderHelper::encrypt($value, self::key());
    }

    /**
     * 解密密文；解密失败时返回空字符串，避免把异常细节暴露给后台列表。
     */
    public static function decrypt(?string $value): string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }

        try {
            return (string)CoderHelper::decrypt($value, self::key());
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * 将敏感字段展示为固定掩码。
     */
    public static function mask(?string $value): string
    {
        return trim((string)$value) === '' ? '' : '******';
    }

    /**
     * 判断前端提交的值是否为掩码，掩码表示保持原密文不变。
     */
    public static function isMask(mixed $value): bool
    {
        return is_string($value) && preg_match('/^\*{3,}$/', trim($value)) === 1;
    }

    /**
     * 获取插件加密密钥，优先复用系统 JWT 密钥。
     */
    private static function key(): string
    {
        $key = (string)config('jwt.secret', '');

        return $key !== '' ? $key : 'smart_admin-wechat-secret';
    }
}
