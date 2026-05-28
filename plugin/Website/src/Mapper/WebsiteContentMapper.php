<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Plugin\Website\Mapper;

use Hyperf\Database\Model\Builder;
use Library\Constants\DataField;
use Library\Constants\Status;
use Library\CoreMapper;
use Plugin\Website\Model\WebsiteContent;
use Plugin\Website\Model\WebsiteSite;
use Plugin\Website\Support\RichText;
use Plugin\Website\Support\WebsitePublishStatus;

/**
 * 官网内容 Mapper。
 */
final class WebsiteContentMapper extends CoreMapper
{
    public function __construct(
        protected string $model = WebsiteContent::class
    ) {}

    public function handleSearch(Builder $query, array $params): Builder
    {
        return _query($query, $params)
            ->like('title|slug|route|summary#keyword')
            ->like('title,slug,route,type,publish_status')
            ->equal('site_id,channel_id,type,publish_status,status,is_top')
            ->in('site_id#site_ids,channel_id#channel_ids,status#status_ids')
            ->dateBetween('published_at')
            ->dateBetween('created_at')
            ->getQuery()
            ->with(['site' => fn ($query) => $query->select(['id', 'code', 'name']), 'channel' => fn ($query) => $query->select(['id', 'site_id', 'code', 'name'])]);
    }

    protected function handleListItems(array $items, array $params = []): array
    {
        return array_map(static function (WebsiteContent $content): array {
            $data = $content->toArray();
            $data['content_text'] = RichText::plainText((string)($content->content_html ?? ''), 300);
            $data['publish_status_text'] = WebsitePublishStatus::label((string)$content->publish_status);

            return $data;
        }, $items);
    }

    /**
     * 内容选项用于导航、关联推荐等轻量选择场景，不返回富文本正文和扩展 JSON。
     *
     * @return array<int, array<string, mixed>>
     */
    public function options(array $params = []): array
    {
        return $this->makeQuery($params, true)
            ->where('status', Status::ENABLED)
            ->limit(max(1, min((int)($params['limit'] ?? 200), 500)))
            ->get(['id', 'site_id', 'channel_id', 'type', 'title', 'slug', 'route'])
            ->map(static fn (WebsiteContent $content): array => [
                'id' => (int)$content->id,
                'value' => (int)$content->id,
                'label' => (string)$content->title,
                'site_id' => (int)$content->site_id,
                'channel_id' => (int)$content->channel_id,
                'type' => (string)$content->type,
                'title' => (string)$content->title,
                'slug' => (string)$content->slug,
                'route' => (string)$content->route,
            ])
            ->all();
    }

    public function publicQuery(WebsiteSite $site): Builder
    {
        $now = date('Y-m-d H:i:s');

        return WebsiteContent::query()
            ->withoutGlobalScope(DataField::TENANT)
            ->where('tenant_id', (int)$site->tenant_id)
            ->where('site_id', (int)$site->id)
            ->where('status', Status::ENABLED)
            ->whereIn('publish_status', [WebsitePublishStatus::PUBLISHED, WebsitePublishStatus::SCHEDULED])
            ->where('published_at', '<=', $now)
            ->where(static function (Builder $builder) use ($now): void {
                $builder->whereNull('offline_at')->orWhere('offline_at', '>', $now);
            });
    }
}
