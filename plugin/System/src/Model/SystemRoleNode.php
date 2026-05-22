<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://github.com/zoujingli/SmartAdmin/blob/master/readme.md
 */

namespace System\Model;

use Carbon\Carbon;
use Hyperf\Database\Model\Relations\BelongsTo;
use Library\CoreModel;

/**
 * @property int $id 主键ID
 * @property int $role_id 角色ID
 * @property int $node_id 节点ID
 * @property int $tenant_id 租户ID
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 * @property-read null|SystemRole $role 
 * @property-read null|SystemNode $node 
 */
final class SystemRoleNode extends CoreModel
{
    protected ?string $table = 'system_role_node';

    protected array $fillable = ['id', 'role_id', 'node_id', 'tenant_id', 'created_at', 'updated_at'];

    protected array $casts = ['id' => 'integer', 'role_id' => 'integer', 'node_id' => 'integer', 'tenant_id' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];

    /**
     * 关联角色。
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(SystemRole::class, 'role_id', 'id');
    }

    /**
     * 关联权限节点。
     */
    public function node(): BelongsTo
    {
        return $this->belongsTo(SystemNode::class, 'node_id', 'id');
    }
}
