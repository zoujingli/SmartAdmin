<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace System\Service;

use Hyperf\Context\Context;
use Library\Constants\Status;
use Library\Constants\System;
use System\Mapper\UserMapper;
use System\Model\SystemNode;
use System\Model\SystemUser;

/**
 * System 后台用户权限码查询服务。
 *
 * 只汇总 System RBAC 中启用角色与启用节点；ProjectRole 权限矩阵不进入这里，避免前后台同名权限码混源。
 */
final class UserAccessCodeService
{
    public function __construct(
        private readonly UserMapper $mapper,
    ) {}

    /**
     * 获取用户可访问权限码集合。
     *
     * 超级管理员返回 `*`，普通用户按启用角色与启用节点汇总。
     *
     * @return array<int, string>
     */
    public function getUserAccessCodes(int $userId): array
    {
        $cacheKey = "user_access_codes_{$userId}";
        $cached = Context::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        if (System::getSuperId() === $userId) {
            $codes = ['*'];
            Context::set($cacheKey, $codes);

            return $codes;
        }

        $user = $this->mapper->read($userId, ['*'], false);
        if (!$user instanceof SystemUser) {
            return [];
        }
        $user->load('roles');

        $baseCodes = ['user:profile'];
        $roleIds = $user->roles()
            ->where('system_role.status', Status::ENABLED)
            ->pluck('system_role.id')
            ->toArray();

        if ($roleIds === []) {
            Context::set($cacheKey, $baseCodes);

            return $baseCodes;
        }

        $nodes = SystemNode::query()
            ->where('system_node.status', Status::ENABLED)
            ->whereHas('roles', function ($query) use ($roleIds) {
                $query->whereIn('system_role.id', $roleIds)
                    ->where('system_role.status', Status::ENABLED);
            })
            ->pluck('system_node.node')
            ->toArray();

        $nodes = array_values(array_unique(array_filter(array_map('strval', $nodes))));
        $codes = array_values(array_unique(array_merge($baseCodes, $nodes)));

        Context::set($cacheKey, $codes);

        return $codes;
    }
}
