<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace System\Support\Scheduler;

use Library\Exception\ErrorResponseException;

/**
 * 表单化周期配置解析器。
 *
 * 后台只保存有限周期类型，避免把原始 cron 表达式作为可执行输入暴露给用户。
 */
final class ScheduleCalculator
{
    public const TYPE_EVERY_MINUTES = 'every_minutes';

    public const TYPE_HOURLY = 'hourly';

    public const TYPE_DAILY = 'daily';

    public const TYPE_WEEKLY = 'weekly';

    public const TYPE_MONTHLY = 'monthly';

    /**
     * @return array<int, array{label:string,value:string}>
     */
    public static function types(): array
    {
        return [
            ['label' => '每 N 分钟', 'value' => self::TYPE_EVERY_MINUTES],
            ['label' => '每小时', 'value' => self::TYPE_HOURLY],
            ['label' => '每天', 'value' => self::TYPE_DAILY],
            ['label' => '每周', 'value' => self::TYPE_WEEKLY],
            ['label' => '每月', 'value' => self::TYPE_MONTHLY],
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public static function normalize(string $type, array $config): array
    {
        return match ($type) {
            self::TYPE_EVERY_MINUTES => [
                'interval' => max(1, min(1440, (int)($config['interval'] ?? 5))),
            ],
            self::TYPE_HOURLY => [
                'minute' => max(0, min(59, (int)($config['minute'] ?? 0))),
            ],
            self::TYPE_DAILY => self::normalizeTime($config),
            self::TYPE_WEEKLY => [
                'weekday' => max(1, min(7, (int)($config['weekday'] ?? 1))),
                ...self::normalizeTime($config),
            ],
            self::TYPE_MONTHLY => [
                'day' => max(1, min(31, (int)($config['day'] ?? 1))),
                ...self::normalizeTime($config),
            ],
            default => throw new ErrorResponseException('计划周期类型不支持'),
        };
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function nextRunAt(string $type, array $config, ?string $baseTime = null): string
    {
        $base = strtotime($baseTime ?: date('Y-m-d H:i:s'));
        if ($base === false) {
            $base = time();
        }

        return match ($type) {
            self::TYPE_EVERY_MINUTES => self::nextEveryMinutes($base, (int)($config['interval'] ?? 5)),
            self::TYPE_HOURLY => self::nextHourly($base, (int)($config['minute'] ?? 0)),
            self::TYPE_DAILY => self::nextDaily($base, (int)($config['hour'] ?? 0), (int)($config['minute'] ?? 0)),
            self::TYPE_WEEKLY => self::nextWeekly($base, (int)($config['weekday'] ?? 1), (int)($config['hour'] ?? 0), (int)($config['minute'] ?? 0)),
            self::TYPE_MONTHLY => self::nextMonthly($base, (int)($config['day'] ?? 1), (int)($config['hour'] ?? 0), (int)($config['minute'] ?? 0)),
            default => throw new ErrorResponseException('计划周期类型不支持'),
        };
    }

    public static function describe(string $type, array $config): string
    {
        return match ($type) {
            self::TYPE_EVERY_MINUTES => sprintf('每 %d 分钟执行一次', (int)($config['interval'] ?? 5)),
            self::TYPE_HOURLY => sprintf('每小时第 %02d 分钟执行', (int)($config['minute'] ?? 0)),
            self::TYPE_DAILY => sprintf('每天 %02d:%02d 执行', (int)($config['hour'] ?? 0), (int)($config['minute'] ?? 0)),
            self::TYPE_WEEKLY => sprintf('每周%s %02d:%02d 执行', self::weekdayLabel((int)($config['weekday'] ?? 1)), (int)($config['hour'] ?? 0), (int)($config['minute'] ?? 0)),
            self::TYPE_MONTHLY => sprintf('每月 %d 日 %02d:%02d 执行', (int)($config['day'] ?? 1), (int)($config['hour'] ?? 0), (int)($config['minute'] ?? 0)),
            default => '未知周期',
        };
    }

    /**
     * @param array<string, mixed> $config
     * @return array{hour:int,minute:int}
     */
    private static function normalizeTime(array $config): array
    {
        return [
            'hour' => max(0, min(23, (int)($config['hour'] ?? 0))),
            'minute' => max(0, min(59, (int)($config['minute'] ?? 0))),
        ];
    }

    private static function nextEveryMinutes(int $base, int $interval): string
    {
        $interval = max(1, min(1440, $interval));
        $next = $base - ($base % ($interval * 60)) + ($interval * 60);

        return date('Y-m-d H:i:s', $next);
    }

    private static function nextHourly(int $base, int $minute): string
    {
        $minute = max(0, min(59, $minute));
        $candidate = mktime((int)date('H', $base), $minute, 0, (int)date('m', $base), (int)date('d', $base), (int)date('Y', $base));
        if ($candidate <= $base) {
            $candidate = strtotime('+1 hour', $candidate) ?: ($base + 3600);
        }

        return date('Y-m-d H:i:s', $candidate);
    }

    private static function nextDaily(int $base, int $hour, int $minute): string
    {
        $candidate = mktime($hour, $minute, 0, (int)date('m', $base), (int)date('d', $base), (int)date('Y', $base));
        if ($candidate <= $base) {
            $candidate = strtotime('+1 day', $candidate) ?: ($base + 86400);
        }

        return date('Y-m-d H:i:s', $candidate);
    }

    private static function nextWeekly(int $base, int $weekday, int $hour, int $minute): string
    {
        $weekday = max(1, min(7, $weekday));
        $current = (int)date('N', $base);
        $days = ($weekday - $current + 7) % 7;
        $candidate = mktime($hour, $minute, 0, (int)date('m', $base), (int)date('d', $base) + $days, (int)date('Y', $base));
        if ($candidate <= $base) {
            $candidate = strtotime('+7 days', $candidate) ?: ($base + 604800);
        }

        return date('Y-m-d H:i:s', $candidate);
    }

    private static function nextMonthly(int $base, int $day, int $hour, int $minute): string
    {
        $day = max(1, min(31, $day));
        $year = (int)date('Y', $base);
        $month = (int)date('m', $base);
        $candidate = self::monthlyCandidate($year, $month, $day, $hour, $minute);
        if ($candidate <= $base) {
            ++$month;
            if ($month > 12) {
                $month = 1;
                ++$year;
            }
            $candidate = self::monthlyCandidate($year, $month, $day, $hour, $minute);
        }

        return date('Y-m-d H:i:s', $candidate);
    }

    private static function monthlyCandidate(int $year, int $month, int $day, int $hour, int $minute): int
    {
        $maxDay = (int)date('t', mktime(0, 0, 0, $month, 1, $year));

        return mktime($hour, $minute, 0, $month, min($day, $maxDay), $year);
    }

    private static function weekdayLabel(int $weekday): string
    {
        return ['一', '二', '三', '四', '五', '六', '日'][max(1, min(7, $weekday)) - 1];
    }
}
