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
use Hyperf\Database\Model\Relations\HasMany;
use Hyperf\Database\Model\SoftDeletes;
use Library\Constants\Status;
use Library\CoreModel;

/**
 * @property int $id 主键ID
 * @property int $tenant_id 租户ID
 * @property string $title 公告标题
 * @property string $content 公告内容
 * @property string $level 公告级别(info信息,success成功,warning警告,error错误)
 * @property int $status 状态(1启用,0禁用)
 * @property string $published_at 发布时间
 * @property string $expired_at 过期时间
 * @property string $link 附加跳转链接
 * @property int $created_by 创建者
 * @property int $updated_by 更新者
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 * @property string $deleted_at 删除时间
 * @property-read null|Collection|SystemNoticeUser[] $recipients 
 * @property-read string $status_text 
 */
final class SystemNotice extends CoreModel
{
    use SoftDeletes;

    protected array $fillable = ['id', 'tenant_id', 'title', 'content', 'level', 'status', 'published_at', 'expired_at', 'link', 'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at'];

    protected array $casts = ['id' => 'integer', 'tenant_id' => 'integer', 'status' => 'integer', 'created_by' => 'integer', 'updated_by' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];

    protected array $logRules = [
        'name' => '系统公告',
        'title' => 'title',
        'fields' => [
            'title' => '公告标题',
            'content' => '公告内容',
            'level' => '公告级别',
            'status' => ['name' => '状态', 'values' => [Status::DISABLED => '禁用', Status::ENABLED => '启用']],
            'published_at' => '发布时间',
            'expired_at' => '过期时间',
            'link' => '跳转链接',
        ],
    ];

    /**
     * 公告接收人关联。
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(SystemNoticeUser::class, 'notice_id', 'id');
    }

    /**
     * 公告状态文本访问器。
     */
    public function getStatusTextAttribute(): string
    {
        return Status::getText((int)$this->status);
    }
}
