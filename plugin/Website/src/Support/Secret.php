<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Plugin\Website\Support;

use Library\Helper\CoderHelper;

use function Hyperf\Config\config;

/**
 * Website 插件敏感字段加解密工具。
 *
 * 开放 API 的 app_key 只允许密文入库；创建或重置时明文只返回一次，后台列表和详情统一展示掩码，
 * 避免第三方调用凭证通过数据库、日志或普通查询接口泄露。
 */
final class Secret
{
    public static function encrypt(?string $value): string
    {
        $value = trim((string)$value);

        return $value === '' ? '' : CoderHelper::encrypt($value, self::key());
    }

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

    public static function mask(?string $value): string
    {
        return trim((string)$value) === '' ? '' : '******';
    }

    public static function isMask(mixed $value): bool
    {
        return is_string($value) && preg_match('/^\*{3,}$/', trim($value)) === 1;
    }

    private static function key(): string
    {
        $key = (string)config('jwt.secret', '');

        return $key !== '' ? $key : 'smart_admin-website-secret';
    }
}
