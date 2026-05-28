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
 * 官网内容发布状态。
 *
 * 草稿、定时、已发布、已下线是公开接口筛选和后台发布动作的唯一状态集合；
 * 公开读取只允许命中已到发布时间且未过下线时间的数据。
 */
final class WebsitePublishStatus
{
    public const DRAFT = 'draft';

    public const SCHEDULED = 'scheduled';

    public const PUBLISHED = 'published';

    public const OFFLINE = 'offline';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [self::DRAFT, self::SCHEDULED, self::PUBLISHED, self::OFFLINE];
    }

    public static function isValid(string $status): bool
    {
        return in_array($status, self::all(), true);
    }

    public static function label(string $status): string
    {
        return [
            self::DRAFT => '草稿',
            self::SCHEDULED => '定时发布',
            self::PUBLISHED => '已发布',
            self::OFFLINE => '已下线',
        ][$status] ?? '未知';
    }
}
