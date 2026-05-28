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
use Plugin\Website\Model\WebsiteChannel;
use Plugin\Website\Model\WebsiteSite;
use Plugin\Website\Support\WebsiteData;

/**
 * 官网栏目 Mapper。
 */
final class WebsiteChannelMapper extends CoreMapper
{
    public function __construct(
        protected string $model = WebsiteChannel::class
    ) {}

    public function handleSearch(Builder $query, array $params): Builder
    {
        return _query($query, $params)
            ->like('code|name|route#keyword')
            ->like('code,name,route,type')
            ->equal('site_id,parent_id,status,type')
            ->in('site_id#site_ids,status#status_ids')
            ->dateBetween('created_at')
            ->getQuery()
            ->with(['site' => fn ($query) => $query->select(['id', 'code', 'name'])]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function tree(array $params = [], bool $isScope = true): array
    {
        $rows = $this->makeQuery($params, $isScope)->get()->map(static fn (WebsiteChannel $channel): array => $channel->toArray())->all();

        return $this->buildTree($rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function publicTree(WebsiteSite $site): array
    {
        $rows = WebsiteChannel::query()
            ->withoutGlobalScope(DataField::TENANT)
            ->where('tenant_id', (int)$site->tenant_id)
            ->where('site_id', (int)$site->id)
            ->where('status', Status::ENABLED)
            ->orderBy('sort', 'desc')
            ->orderBy('id')
            ->get()
            ->map(static fn (WebsiteChannel $channel): array => $channel->toArray())
            ->all();

        return $this->buildTree($rows);
    }

    /**
     * @return array<int, int>
     */
    public function publicChannelIds(WebsiteSite $site, string $codeOrRoute): array
    {
        $value = trim($codeOrRoute);
        if ($value === '') {
            return [];
        }

        $route = WebsiteData::route($value);
        $rows = WebsiteChannel::query()
            ->withoutGlobalScope(DataField::TENANT)
            ->where('tenant_id', (int)$site->tenant_id)
            ->where('site_id', (int)$site->id)
            ->where('status', Status::ENABLED)
            ->get(['id', 'parent_id', 'code', 'route'])
            ->map(static fn (WebsiteChannel $channel): array => $channel->toArray())
            ->all();

        $matched = [];
        foreach ($rows as $row) {
            if ((string)($row['code'] ?? '') === $value || (string)($row['route'] ?? '') === $route) {
                $matched[] = (int)$row['id'];
            }
        }
        if ($matched === []) {
            return [];
        }

        $ids = $matched;
        do {
            $before = count($ids);
            foreach ($rows as $row) {
                if (in_array((int)($row['parent_id'] ?? 0), $ids, true)) {
                    $ids[] = (int)$row['id'];
                }
            }
            $ids = array_values(array_unique(array_filter($ids)));
        } while (count($ids) > $before);

        return $ids;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function options(array $params = []): array
    {
        return $this->makeQuery($params, true)
            ->where('status', Status::ENABLED)
            ->limit(max(1, min((int)($params['limit'] ?? 200), 500)))
            ->get(['id', 'site_id', 'parent_id', 'code', 'name', 'route'])
            ->map(static function (WebsiteChannel $channel): array {
                $route = (string)$channel->route;

                return [
                    'id' => (int)$channel->id,
                    'value' => (int)$channel->id,
                    'label' => $route === '' ? (string)$channel->name : sprintf('%s（%s）', (string)$channel->name, $route),
                    'site_id' => (int)$channel->site_id,
                    'parent_id' => (int)$channel->parent_id,
                    'code' => (string)$channel->code,
                    'name' => (string)$channel->name,
                    'route' => $route,
                ];
            })
            ->all();
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
