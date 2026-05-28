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
use Plugin\Website\Support\WebsiteData;

/**
 * 官网栏目模型。
 */
final class WebsiteChannel extends CoreModel
{
    use SoftDeletes;

    protected ?string $table = 'website_channel';

    protected array $hidden = ['deleted_at'];

    protected array $fillable = ['id', 'tenant_id', 'site_id', 'parent_id', 'code', 'name', 'route', 'type', 'seo', 'sort', 'status', 'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at'];

    protected array $casts = ['id' => 'integer', 'tenant_id' => 'integer', 'site_id' => 'integer', 'parent_id' => 'integer', 'sort' => 'integer', 'status' => 'integer', 'created_by' => 'integer', 'updated_by' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];

    protected array $logRules = [
        'name' => '官网栏目',
        'title' => 'name',
        'fields' => [
            'site_id' => '所属站点',
            'parent_id' => '父级栏目',
            'code' => '栏目编码',
            'name' => '栏目名称',
            'route' => '栏目路由',
            'type' => '栏目类型',
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

    public function getSeoAttribute(mixed $value): array
    {
        return WebsiteData::object($value);
    }

    public function setSeoAttribute(mixed $value): void
    {
        $this->attributes['seo'] = WebsiteData::encodeObject($value);
    }
}
