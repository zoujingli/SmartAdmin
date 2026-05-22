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
 * @property int $tenant_id 租户ID
 * @property int $notice_id 公告ID
 * @property int $user_id 收件用户ID
 * @property int $is_read 是否已读(1已读,0未读)
 * @property string $read_at 已读时间
 * @property string $archived_at 归档时间
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 * @property-read null|SystemNotice $notice 
 * @property-read null|SystemUser $user 
 */
final class SystemNoticeUser extends CoreModel
{
    protected array $fillable = ['id', 'tenant_id', 'notice_id', 'user_id', 'is_read', 'read_at', 'archived_at', 'created_at', 'updated_at'];

    protected array $casts = ['id' => 'integer', 'tenant_id' => 'integer', 'notice_id' => 'integer', 'user_id' => 'integer', 'is_read' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];

    protected array $hidden = [];

    /**
     * 关联公告。
     */
    public function notice(): BelongsTo
    {
        return $this->belongsTo(SystemNotice::class, 'notice_id', 'id');
    }

    /**
     * 关联接收用户。
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(SystemUser::class, 'user_id', 'id');
    }
}
