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

use Hyperf\Database\Model\Builder;
use Library\Constants\DataField;
use Library\Constants\System;
use System\Model\SystemUser;

/**
 * 操作审计日志范围。
 *
 * 操作日志 action 与变更日志 change 必须保持同一可见性：平台超级管理员查看完整审计链，
 * 普通租户用户和 SaaS 子超管仍由租户全局范围限制在自己租户内。
 */
final class AuditLogScope
{
    public static function apply(Builder $query, bool $isScope): Builder
    {
        if (!$isScope || self::isPlatformSuperUser()) {
            return $query->withoutGlobalScope(DataField::TENANT);
        }

        return $query;
    }

    public static function isPlatformSuperUser(): bool
    {
        try {
            $user = user(SystemUser::class);
        } catch (\Throwable) {
            return false;
        }

        return $user instanceof SystemUser
            && $user->isSuper()
            && (int)$user->getId() === System::getSuperId();
    }
}
