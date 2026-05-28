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

/**
 * 官网访客线索处理状态。
 */
final class WebsiteLeadStatus
{
    public const PENDING = 'pending';

    public const PROCESSING = 'processing';

    public const HANDLED = 'handled';

    public const INVALID = 'invalid';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [self::PENDING, self::PROCESSING, self::HANDLED, self::INVALID];
    }

    public static function isValid(string $status): bool
    {
        return in_array($status, self::all(), true);
    }

    public static function label(string $status): string
    {
        return [
            self::PENDING => '待处理',
            self::PROCESSING => '处理中',
            self::HANDLED => '已处理',
            self::INVALID => '无效线索',
        ][$status] ?? '未知';
    }
}
