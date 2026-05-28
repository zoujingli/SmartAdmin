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
use Hyperf\Database\Model\SoftDeletes;
use Library\Constants\Status;
use Library\CoreModel;
use Plugin\Website\Support\WebsiteData;
use Plugin\Website\Support\WebsitePublishStatus;

/**
 * 官网通用内容模型。
 */
final class WebsiteContent extends CoreModel
{
    use SoftDeletes;

    protected ?string $table = 'website_content';

    protected array $hidden = ['deleted_at'];

    protected array $fillable = ['id', 'tenant_id', 'site_id', 'channel_id', 'type', 'title', 'slug', 'route', 'summary', 'cover', 'content_html', 'payload', 'tags', 'seo', 'sort', 'is_top', 'publish_status', 'published_at', 'offline_at', 'status', 'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at'];

    protected array $casts = ['id' => 'integer', 'tenant_id' => 'integer', 'site_id' => 'integer', 'channel_id' => 'integer', 'sort' => 'integer', 'is_top' => 'integer', 'status' => 'integer', 'published_at' => 'datetime', 'offline_at' => 'datetime', 'created_by' => 'integer', 'updated_by' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];

    protected array $logRules = [
        'name' => '官网内容',
        'title' => 'title',
        'fields' => [
            'site_id' => '所属站点',
            'channel_id' => '所属栏目',
            'type' => '内容类型',
            'title' => '内容标题',
            'slug' => '访问标识',
            'route' => '访问路由',
            'summary' => '内容摘要',
            'sort' => '排序',
            'is_top' => ['name' => '是否置顶', 'values' => [0 => '否', 1 => '是']],
            'publish_status' => ['name' => '发布状态', 'values' => [
                WebsitePublishStatus::DRAFT => '草稿',
                WebsitePublishStatus::SCHEDULED => '定时发布',
                WebsitePublishStatus::PUBLISHED => '已发布',
                WebsitePublishStatus::OFFLINE => '已下线',
            ]],
            'status' => ['name' => '状态', 'values' => [Status::DISABLED => '禁用', Status::ENABLED => '启用']],
        ],
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(WebsiteSite::class, 'site_id', 'id');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(WebsiteChannel::class, 'channel_id', 'id');
    }

    public function getPayloadAttribute(mixed $value): array
    {
        return WebsiteData::object($value);
    }

    public function setPayloadAttribute(mixed $value): void
    {
        $this->attributes['payload'] = WebsiteData::encodeObject($value);
    }

    public function getTagsAttribute(mixed $value): array
    {
        return WebsiteData::stringList($value);
    }

    public function setTagsAttribute(mixed $value): void
    {
        $this->attributes['tags'] = WebsiteData::encodeList($value);
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
