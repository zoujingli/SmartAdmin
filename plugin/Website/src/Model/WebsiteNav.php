<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Plugin\Website\Model;

use Hyperf\Database\Model\Relations\BelongsTo;
use Hyperf\Database\Model\Relations\HasMany;
use Hyperf\Database\Model\SoftDeletes;
use Library\Constants\Status;
use Library\CoreModel;

/**
 * 官网导航模型。
 */
final class WebsiteNav extends CoreModel
{
    use SoftDeletes;

    protected ?string $table = 'website_nav';

    protected array $hidden = ['deleted_at'];

    protected array $fillable = ['id', 'tenant_id', 'site_id', 'parent_id', 'position', 'title', 'link_type', 'route', 'url', 'channel_id', 'content_id', 'target', 'sort', 'status', 'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at'];

    protected array $casts = ['id' => 'integer', 'tenant_id' => 'integer', 'site_id' => 'integer', 'parent_id' => 'integer', 'channel_id' => 'integer', 'content_id' => 'integer', 'sort' => 'integer', 'status' => 'integer', 'created_by' => 'integer', 'updated_by' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];

    protected array $logRules = [
        'name' => '官网导航',
        'title' => 'title',
        'fields' => [
            'site_id' => '所属站点',
            'position' => '导航位置',
            'title' => '导航标题',
            'link_type' => '链接类型',
            'route' => '站内路由',
            'url' => '外部地址',
            'target' => '打开方式',
            'sort' => '排序',
            'status' => ['name' => '状态', 'values' => [Status::DISABLED => '禁用', Status::ENABLED => '启用']],
        ],
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(WebsiteSite::class, 'site_id', 'id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id', 'id');
    }
}
