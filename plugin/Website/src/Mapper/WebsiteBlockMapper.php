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
use Plugin\Website\Model\WebsiteBlock;
use Plugin\Website\Model\WebsiteSite;
use Plugin\Website\Support\WebsitePublishStatus;

/**
 * 官网页面区块 Mapper。
 */
final class WebsiteBlockMapper extends CoreMapper
{
    public function __construct(
        protected string $model = WebsiteBlock::class
    ) {}

    public function handleSearch(Builder $query, array $params): Builder
    {
        return _query($query, $params)
            ->like('code|name|title|page_code|group_code#keyword')
            ->like('code,name,title,page_code,group_code,type,publish_status')
            ->equal('site_id,page_code,group_code,type,publish_status,status')
            ->in('site_id#site_ids,status#status_ids')
            ->dateBetween('published_at')
            ->dateBetween('created_at')
            ->getQuery()
            ->with(['site' => fn ($query) => $query->select(['id', 'code', 'name'])]);
    }

    protected function handleListItems(array $items, array $params = []): array
    {
        return array_map(static function (WebsiteBlock $block): array {
            $data = $block->toArray();
            $data['publish_status_text'] = WebsitePublishStatus::label((string)$block->publish_status);

            return $data;
        }, $items);
    }

    /**
     * 区块选项只返回轻量字段，供前端选择器按站点、页面和分组快速定位区块。
     *
     * @return array<int, array<string, mixed>>
     */
    public function options(array $params = []): array
    {
        return $this->makeQuery($params, true)
            ->where('status', Status::ENABLED)
            ->limit(max(1, min((int)($params['limit'] ?? 200), 500)))
            ->get(['id', 'site_id', 'page_code', 'group_code', 'code', 'name', 'title'])
            ->map(static fn (WebsiteBlock $block): array => [
                'id' => (int)$block->id,
                'value' => (int)$block->id,
                'label' => trim(sprintf('%s %s', (string)$block->name, (string)$block->code)),
                'site_id' => (int)$block->site_id,
                'page_code' => (string)$block->page_code,
                'group_code' => (string)$block->group_code,
                'code' => (string)$block->code,
                'name' => (string)$block->name,
                'title' => (string)$block->title,
            ])
            ->all();
    }

    public function publicQuery(WebsiteSite $site): Builder
    {
        $now = date('Y-m-d H:i:s');

        return WebsiteBlock::query()
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
