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
use Hyperf\Database\Model\Collection;
use Hyperf\Database\Model\Relations\BelongsToMany;
use Hyperf\Database\Model\SoftDeletes;
use Library\Constants\Status;
use Library\CoreModel;

/**
 * @property int $id 主键ID
 * @property int $tenant_id 租户ID
 * @property string $code 岗位编码
 * @property string $name 岗位名称
 * @property int $sort 排序权重
 * @property int $status 状态(1启用,0禁用)
 * @property string $remark 备注
 * @property int $created_by 创建者
 * @property int $updated_by 更新者
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 * @property string $deleted_at 删除时间
 * @property-read null|Collection|SystemUser[] $users 
 * @property-read string $status_text 
 */
final class SystemPost extends CoreModel
{
    use SoftDeletes;

    protected array $fillable = ['id', 'tenant_id', 'code', 'name', 'sort', 'status', 'remark', 'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at'];

    protected array $logRules = [
        'name' => '系统岗位',
        'title' => 'name',
        'fields' => [
            'code' => '岗位编码',
            'name' => '岗位名称',
            'sort' => '排序',
            'status' => ['name' => '状态', 'values' => [Status::DISABLED => '禁用', Status::ENABLED => '启用']],
            'remark' => '备注',
        ],
    ];

    /**
     * 岗位与用户的多对多关系。
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(SystemUser::class, 'system_user_post', 'post_id', 'user_id');
    }

    /**
     * 岗位状态文本访问器。
     */
    public function getStatusTextAttribute(): string
    {
        return Status::getText((int)$this->status);
    }
}
