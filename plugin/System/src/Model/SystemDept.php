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
use Hyperf\Database\Model\Relations\BelongsTo;
use Hyperf\Database\Model\Relations\BelongsToMany;
use Hyperf\Database\Model\Relations\HasMany;
use Hyperf\Database\Model\SoftDeletes;
use Library\Constants\Status;
use Library\CoreModel;
use Library\Traits\ModelDeptTrait;

/**
 * @property int $id 主键ID
 * @property int $tenant_id 租户ID
 * @property int $pid 上级部门ID
 * @property string $code 部门编码
 * @property string $name 部门名称
 * @property string $phone 联系电话
 * @property string $email 部门邮箱
 * @property string $level 层级路径
 * @property string $leader 负责人
 * @property int $sort 排序权重
 * @property int $status 状态(1启用,0禁用)
 * @property string $remark 备注
 * @property int $created_by 创建者
 * @property int $updated_by 更新者
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 * @property string $deleted_at 删除时间
 * @property-read string $full_path
 * @property-read int $depth
 * @property-read null|SystemDept $parent
 * @property-read null|Collection|SystemDept[] $children
 * @property-read null|Collection|SystemUser[] $users
 * @property-read string $status_text
 */
final class SystemDept extends CoreModel
{
    use SoftDeletes;
    use ModelDeptTrait;

    protected array $fillable = ['id', 'tenant_id', 'pid', 'code', 'name', 'phone', 'email', 'level', 'leader', 'sort', 'status', 'remark', 'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at'];

    protected array $logRules = [
        'name' => '系统部门',
        'title' => 'name',
        'fields' => [
            'pid' => '上级部门',
            'code' => '部门编码',
            'name' => '部门名称',
            'phone' => '联系电话',
            'email' => '部门邮箱',
            'level' => '层级路径',
            'leader' => '负责人',
            'sort' => '排序',
            'status' => ['name' => '状态', 'values' => [Status::DISABLED => '禁用', Status::ENABLED => '启用']],
            'remark' => '备注',
        ],
    ];

    /**
     * 上级部门关联。
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(SystemDept::class, 'pid', 'id');
    }

    /**
     * 下级部门集合。
     */
    public function children(): HasMany
    {
        return $this->hasMany(SystemDept::class, 'pid', 'id')
            ->orderBy('sort', 'desc')
            ->orderBy('id', 'desc');
    }

    /**
     * 部门下用户关联。
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(SystemUser::class, 'system_user_dept', 'dept_id', 'user_id');
    }

    /**
     * 状态文本访问器。
     */
    public function getStatusTextAttribute(): string
    {
        return Status::getText((int)$this->status);
    }
}
