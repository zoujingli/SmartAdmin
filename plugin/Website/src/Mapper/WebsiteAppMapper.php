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
use Library\CoreMapper;
use Plugin\Website\Model\WebsiteApp;
use Plugin\Website\Support\Secret;
use Plugin\Website\Support\WebsiteOpenApiScope;

/**
 * 官网开放接口应用 Mapper。
 */
final class WebsiteAppMapper extends CoreMapper
{
    public function __construct(
        protected string $model = WebsiteApp::class
    ) {}

    public function handleSearch(Builder $query, array $params): Builder
    {
        return _query($query, $params)
            ->like('name|app_id|remark#keyword')
            ->like('name,app_id,remark,last_used_ip')
            ->equal('site_id,status')
            ->in('site_id#site_ids,status#status_ids')
            ->dateBetween('created_at')
            ->getQuery()
            ->with(['site' => fn ($query) => $query->select(['id', 'code', 'name'])]);
    }

    protected function handleListItems(array $items, array $params = []): array
    {
        return array_map(static function (WebsiteApp $app): array {
            $data = $app->toArray();
            $data['app_key'] = Secret::mask((string)$app->getRawOriginal('app_key'));
            $data['scope_texts'] = array_map(WebsiteOpenApiScope::label(...), $app->scopes);

            return $data;
        }, $items);
    }

    /**
     * 开放接口验签没有后台登录上下文，必须跳过租户全局范围后按全局唯一 app_id 查找；
     * 站点与租户边界在验签服务里通过 app.site_id + app.tenant_id 重新收紧。
     */
    public function findForOpenApi(string $appId): ?WebsiteApp
    {
        $appId = strtolower(trim($appId));
        if ($appId === '') {
            return null;
        }

        return WebsiteApp::query()
            ->withoutGlobalScope(DataField::TENANT)
            ->where('app_id', $appId)
            ->first();
    }
}
