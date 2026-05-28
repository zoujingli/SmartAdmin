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
use Library\Constants\Status;
use Library\CoreMapper;
use Plugin\Website\Model\WebsiteSite;

/**
 * 官网站点 Mapper。
 */
final class WebsiteSiteMapper extends CoreMapper
{
    public function __construct(
        protected string $model = WebsiteSite::class
    ) {}

    public function handleSearch(Builder $query, array $params): Builder
    {
        return _query($query, $params)
            ->like('code|name|domain#keyword')
            ->like('code,name,domain')
            ->equal('status')
            ->dateBetween('created_at')
            ->getQuery();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function options(array $params = []): array
    {
        return $this->makeQuery($params, true)
            ->where('status', Status::ENABLED)
            ->limit(max(1, min((int)($params['limit'] ?? 100), 200)))
            ->get(['id', 'code', 'name', 'domain'])
            ->map(static fn (WebsiteSite $site): array => [
                'id' => (int)$site->id,
                'value' => (int)$site->id,
                'label' => (string)$site->name,
                'code' => (string)$site->code,
                'name' => (string)$site->name,
                'domain' => (string)$site->domain,
            ])
            ->all();
    }

}
