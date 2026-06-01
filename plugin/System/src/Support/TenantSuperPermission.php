<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace System\Support;

use Hyperf\Context\Context;
use Library\Constants\Status;
use System\Model\SystemNode;

/**
 * SaaS 子超管权限边界。
 *
 * 子超管在所属租户内自动拥有非平台保留权限；平台保留权限必须由 APP_SUPER_USER 平台超级管理员显式管理。
 */
final class TenantSuperPermission
{
    public const RESERVED_PREFIXES = [
        'system.tenant.',
        'system.menu.',
        'system.setting.',
        'system.data.',
    ];

    private const CONTEXT_CODES_KEY = 'system_tenant_super_permission_codes';

    /**
     * 判断权限码是否属于平台保留能力。
     */
    public static function isReserved(string $code): bool
    {
        $code = trim($code);
        if ($code === '' || $code === '*') {
            return true;
        }

        foreach (self::RESERVED_PREFIXES as $prefix) {
            if (str_starts_with($code, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 判断权限码是否可授予租户子超管。
     */
    public static function isAllowed(string $code): bool
    {
        return !self::isReserved($code);
    }

    /**
     * 过滤出租户子超管可用权限码。
     *
     * @param array<int|string, mixed> $codes
     * @return array<int, string>
     */
    public static function filterCodes(array $codes): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $code): string => trim((string)$code), $codes),
            static fn (string $code): bool => self::isAllowed($code)
        )));
    }

    /**
     * 获取所有启用的非平台保留权限码。
     *
     * @return array<int, string>
     */
    public static function codes(): array
    {
        $cached = Context::get(self::CONTEXT_CODES_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $codes = SystemNode::query()
            ->where('status', Status::ENABLED)
            ->pluck('node')
            ->toArray();

        $codes = self::filterCodes($codes);
        Context::set(self::CONTEXT_CODES_KEY, $codes);

        return $codes;
    }

    /**
     * 获取所有启用的非平台保留权限节点 ID。
     *
     * @return array<int, int>
     */
    public static function nodeIds(?string $prefix = null): array
    {
        return SystemNode::query()
            ->where('status', Status::ENABLED)
            ->get(['id', 'node'])
            ->filter(static function (SystemNode $node) use ($prefix): bool {
                $code = (string)$node->node;
                if ($prefix !== null && !str_starts_with($code, $prefix)) {
                    return false;
                }

                return self::isAllowed($code);
            })
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int)$id)
            ->toArray();
    }
}
