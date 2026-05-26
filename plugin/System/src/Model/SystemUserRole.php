<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace System\Model;

use Library\CoreModel;

/**
 * @property int $user_id 用户ID
 * @property int $role_id 角色ID
 * @property int $tenant_id 租户ID
 */
final class SystemUserRole extends CoreModel
{
    // 关联表由复合主键维护关系，不包含 created_at/updated_at 字段，避免模型保存时写入不存在列。
    public bool $timestamps = false;

    public bool $incrementing = false;

    protected string $primaryKey = 'user_id';

    protected ?string $table = 'system_user_role';

    protected array $fillable = ['user_id', 'role_id', 'tenant_id'];

    protected array $casts = ['user_id' => 'integer', 'role_id' => 'integer', 'tenant_id' => 'integer'];
}
