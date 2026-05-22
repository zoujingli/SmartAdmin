<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://github.com/zoujingli/SmartAdmin/blob/master/readme.md
 */

namespace System\Support;

use Library\Constants\Status;

final class SystemBootstrapSeed
{
    public const SUPER_ROLE_ID = 1;

    public const SUPER_USERNAME = 'admin';

    public const DEFAULT_PASSWORD = 'admin';

    public static function superAdminUserRow(int $userId, ?string $now = null): array
    {
        $now ??= date('Y-m-d H:i:s');

        return [
            'id' => $userId,
            'tenant_id' => 0,
            'username' => self::SUPER_USERNAME,
            'nickname' => '管理员',
            'phone' => '13800138000',
            'email' => 'admin@example.com',
            'password' => password_hash(self::DEFAULT_PASSWORD, PASSWORD_DEFAULT),
            'avatar' => '',
            'signed' => '系统超级管理员',
            'status' => Status::ENABLED,
            'remark' => '初始化超级管理员账号',
            'login_ip' => '127.0.0.1',
            'login_time' => $now,
            'extra' => json_encode([], JSON_UNESCAPED_UNICODE),
            'created_by' => 0,
            'updated_by' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ];
    }

    public static function superAdminRoleRow(int $userId, ?string $now = null): array
    {
        $now ??= date('Y-m-d H:i:s');

        return [
            'id' => self::SUPER_ROLE_ID,
            'tenant_id' => 0,
            'name' => '超级管理员',
            'code' => 'super-admin',
            'scope' => 1,
            'remark' => '初始化超级管理员角色',
            'status' => Status::ENABLED,
            'sort' => 0,
            'created_by' => $userId,
            'updated_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ];
    }

    public static function superAdminRoleBindingRow(int $userId): array
    {
        return [
            'tenant_id' => 0,
            'user_id' => $userId,
            'role_id' => self::SUPER_ROLE_ID,
        ];
    }
}
