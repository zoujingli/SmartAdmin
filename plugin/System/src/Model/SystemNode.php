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

use Carbon\Carbon;
use Hyperf\Database\Model\Collection;
use Hyperf\Database\Model\Relations\BelongsToMany;
use Library\CoreModel;

/**
 * @property int $id 主键ID
 * @property string $node 权限节点编码
 * @property string $name 节点名称
 * @property string $type 节点类型(check授权校验,login登录校验)
 * @property string $source 注册来源(annotation注解,menu菜单,legacy兼容)
 * @property string $ref 来源引用
 * @property int $status 状态(1启用,0禁用)
 * @property string $meta 元数据(JSON)
 * @property int $created_by 创建者
 * @property int $updated_by 更新者
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 * @property-read null|Collection|SystemRole[] $roles
 */
final class SystemNode extends CoreModel
{
    protected ?string $table = 'system_node';

    protected array $fillable = ['id', 'node', 'name', 'type', 'source', 'ref', 'status', 'meta', 'created_by', 'updated_by', 'created_at', 'updated_at'];

    protected array $casts = ['id' => 'integer', 'status' => 'integer', 'created_by' => 'integer', 'updated_by' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];

    /**
     * 节点关联角色集合。
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(SystemRole::class, 'system_role_node', 'node_id', 'role_id')
            ->withTimestamps();
    }
}
