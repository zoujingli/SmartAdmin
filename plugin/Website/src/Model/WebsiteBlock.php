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
 * 官网页面区块模型。
 */
final class WebsiteBlock extends CoreModel
{
    use SoftDeletes;

    protected ?string $table = 'website_block';

    protected array $hidden = ['deleted_at'];

    protected array $fillable = ['id', 'tenant_id', 'site_id', 'page_code', 'group_code', 'code', 'name', 'type', 'title', 'subtitle', 'payload', 'media', 'link', 'sort', 'publish_status', 'published_at', 'offline_at', 'status', 'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at'];

    protected array $casts = ['id' => 'integer', 'tenant_id' => 'integer', 'site_id' => 'integer', 'sort' => 'integer', 'status' => 'integer', 'published_at' => 'datetime', 'offline_at' => 'datetime', 'created_by' => 'integer', 'updated_by' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];

    protected array $logRules = [
        'name' => '官网页面区块',
        'title' => 'name',
        'fields' => [
            'site_id' => '所属站点',
            'page_code' => '页面编码',
            'group_code' => '分组编码',
            'code' => '区块编码',
            'name' => '区块名称',
            'type' => '区块类型',
            'title' => '区块标题',
            'sort' => '排序',
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

    public function getPayloadAttribute(mixed $value): array
    {
        return WebsiteData::object($value);
    }

    public function setPayloadAttribute(mixed $value): void
    {
        $this->attributes['payload'] = WebsiteData::encodeObject($value);
    }

    public function getMediaAttribute(mixed $value): array
    {
        return WebsiteData::object($value);
    }

    public function setMediaAttribute(mixed $value): void
    {
        $this->attributes['media'] = WebsiteData::encodeObject($value);
    }

    public function getLinkAttribute(mixed $value): array
    {
        return WebsiteData::object($value);
    }

    public function setLinkAttribute(mixed $value): void
    {
        $this->attributes['link'] = WebsiteData::encodeObject($value);
    }
}
