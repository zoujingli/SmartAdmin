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
use Plugin\Website\Model\WebsiteNav;
use Plugin\Website\Model\WebsiteSite;

/**
 * 官网导航 Mapper。
 */
final class WebsiteNavMapper extends CoreMapper
{
    public function __construct(
        protected string $model = WebsiteNav::class
    ) {}

    public function handleSearch(Builder $query, array $params): Builder
    {
        return _query($query, $params)
            ->like('title|route|url#keyword')
            ->like('title,route,url,position,link_type')
            ->equal('site_id,parent_id,position,link_type,status')
            ->in('site_id#site_ids,status#status_ids')
            ->dateBetween('created_at')
            ->getQuery();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function tree(array $params = [], bool $isScope = true): array
    {
        $rows = $this->makeQuery($params, $isScope)->get()->map(static fn (WebsiteNav $nav): array => $nav->toArray())->all();

        return $this->buildTree($rows);
    }

    /**
     * 导航选项返回扁平结构，便于编辑父级导航或配置关联关系时选择。
     *
     * @return array<int, array<string, mixed>>
     */
    public function options(array $params = []): array
    {
        return $this->makeQuery($params, true)
            ->where('status', Status::ENABLED)
            ->limit(max(1, min((int)($params['limit'] ?? 200), 500)))
            ->get(['id', 'site_id', 'parent_id', 'position', 'title', 'link_type', 'route', 'url'])
            ->map(static fn (WebsiteNav $nav): array => [
                'id' => (int)$nav->id,
                'value' => (int)$nav->id,
                'label' => (string)$nav->title,
                'site_id' => (int)$nav->site_id,
                'parent_id' => (int)$nav->parent_id,
                'position' => (string)$nav->position,
                'title' => (string)$nav->title,
                'link_type' => (string)$nav->link_type,
                'route' => (string)$nav->route,
                'url' => (string)$nav->url,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function publicTree(WebsiteSite $site, string $position = 'top'): array
    {
        $position = trim($position) ?: 'top';
        $rows = WebsiteNav::query()
            ->withoutGlobalScope(DataField::TENANT)
            ->where('tenant_id', (int)$site->tenant_id)
            ->where('site_id', (int)$site->id)
            ->where('position', $position)
            ->where('status', Status::ENABLED)
            ->orderBy('sort', 'desc')
            ->orderBy('id')
            ->get()
            ->map(static fn (WebsiteNav $nav): array => $nav->toArray())
            ->all();

        return $this->buildTree($rows);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function buildTree(array $rows, int $parentId = 0): array
    {
        $tree = [];
        foreach ($rows as $row) {
            if ((int)($row['parent_id'] ?? 0) !== $parentId) {
                continue;
            }
            $row['children'] = $this->buildTree($rows, (int)$row['id']);
            $tree[] = $row;
        }

        return $tree;
    }
}
