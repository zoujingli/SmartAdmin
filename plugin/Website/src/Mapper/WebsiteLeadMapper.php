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
use Library\CoreMapper;
use Plugin\Website\Model\WebsiteLead;
use Plugin\Website\Support\WebsiteLeadStatus;

/**
 * 官网访客线索 Mapper。
 */
final class WebsiteLeadMapper extends CoreMapper
{
    public function __construct(
        protected string $model = WebsiteLead::class
    ) {}

    public function handleSearch(Builder $query, array $params): Builder
    {
        return _query($query, $params)
            ->like('name|mobile|email|company|subject|content#keyword')
            ->like('name,mobile,email,company,subject,status')
            ->equal('site_id,status')
            ->in('site_id#site_ids,status#statuses')
            ->dateBetween('created_at')
            ->dateBetween('handled_at')
            ->getQuery()
            ->with(['site' => fn ($query) => $query->select(['id', 'code', 'name'])]);
    }

    protected function handleListItems(array $items, array $params = []): array
    {
        return array_map(static function (WebsiteLead $lead): array {
            $data = $lead->toArray();
            $data['status_text'] = WebsiteLeadStatus::label((string)$lead->status);

            return $data;
        }, $items);
    }
}
