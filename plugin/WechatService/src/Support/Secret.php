<?php

declare(strict_types=1);

namespace Plugin\WechatService\Support;

use Library\Helper\CoderHelper;

use function Hyperf\Config\config;

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

        return $key !== '' ? $key : 'smart_admin-wechat-secret';
    }
}
