<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Plugin\Website\Service;

use Library\Constants\Status;
use Library\CoreService;
use Library\Exception\ErrorResponseException;
use Plugin\Website\Mapper\WebsiteChannelMapper;
use Plugin\Website\Model\WebsiteChannel;
use Plugin\Website\Service\Concerns\WebsiteServiceHelpers;
use Plugin\Website\Support\WebsiteData;

/**
 * 官网栏目服务。
 */
final class WebsiteChannelService extends CoreService
{
    use WebsiteServiceHelpers;

    public function __construct(
        protected WebsiteChannelMapper $mapper
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function tree(array $params = []): array
    {
        return $this->mapper->tree($params);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function options(array $params = []): array
    {
        return $this->mapper->options($params);
    }

    protected function filterData(array &$data, array $exists = []): array
    {
        $this->trimStringFields($data, ['code', 'name', 'route', 'type']);
        if (array_key_exists('code', $data)) {
            $data['code'] = strtolower((string)$data['code']);
        }
        $this->normalizeRouteField($data);

        $rules = [
            'site_id.integer' => '所属站点必须为数字',
            'site_id.min:1' => '请选择所属站点',
            'parent_id.integer' => '父级栏目必须为数字',
            'parent_id.min:0' => '父级栏目不能小于 0',
            'code.filled' => '栏目编码不能为空',
            'code.max:80' => '栏目编码最多 80 位',
            'name.filled' => '栏目名称不能为空',
            'name.max:120' => '栏目名称最多 120 位',
            'route.filled' => '栏目路由不能为空',
            'route.max:255' => '栏目路由最多 255 位',
            'type.max:30' => '栏目类型最多 30 位',
            'seo.nullable' => 'SEO 配置格式错误',
            'sort.integer' => '排序必须为数字',
            'status.integer' => '状态必须为数字',
            'status.in:1,0' => '状态值错误',
        ];
        if ($exists === []) {
            $rules['site_id.required'] = '请选择所属站点';
            $rules['code.required'] = '栏目编码不能为空';
            $rules['name.required'] = '栏目名称不能为空';
            $rules['route.required'] = '栏目路由不能为空';
            $rules['parent_id.default'] = 0;
            $rules['type.default'] = 'page';
            $rules['seo.default'] = [];
            $rules['sort.default'] = 0;
            $rules['status.default'] = Status::ENABLED;
        }

        $data = _vali($rules, $data);
        $this->normalizeIntFields($data, ['site_id', 'parent_id', 'sort', 'status']);
        if (array_key_exists('seo', $data)) {
            $data['seo'] = WebsiteData::object($data['seo']);
        }
        $siteId = (int)($data['site_id'] ?? $exists['site_id'] ?? 0);
        $site = $this->ensureSite($siteId);
        // 栏目租户归属始终跟随所属站点，后台表单不暴露 tenant_id，也不能由请求体直接指定。
        $data['tenant_id'] = (int)$site->tenant_id;
        $parentId = (int)($data['parent_id'] ?? $exists['parent_id'] ?? 0);
        if ($parentId > 0) {
            if ($exists !== [] && $parentId === (int)($exists['id'] ?? 0)) {
                throw new ErrorResponseException('父级栏目不能选择自身');
            }
            if ($exists !== []) {
                $this->assertParentDoesNotCreateCycle((int)($exists['id'] ?? 0), $parentId, $siteId);
            }
            $this->ensureChannel($parentId, $siteId, '父级栏目不存在或不属于当前站点');
        }
        $this->ensureUniqueInSite(WebsiteChannel::class, 'code', $data, $exists, '当前站点下栏目编码已存在');
        $this->ensureUniqueInSite(WebsiteChannel::class, 'route', $data, $exists, '当前站点下栏目路由已存在');

        return $data;
    }

    private function assertParentDoesNotCreateCycle(int $currentId, int $parentId, int $siteId): void
    {
        if ($currentId <= 0 || $parentId <= 0) {
            return;
        }

        // 栏目树只允许向上指向祖先链之外的节点；逐级回溯父级，防止把栏目挂到自己的子孙下面形成闭环。
        $parents = [];
        foreach (WebsiteChannel::query()->where('site_id', $siteId)->get(['id', 'parent_id']) as $channel) {
            $parents[(int)$channel->id] = (int)$channel->parent_id;
        }

        $visited = [];
        for ($cursor = $parentId; $cursor > 0;) {
            if ($cursor === $currentId) {
                throw new ErrorResponseException('父级栏目不能选择当前栏目的下级栏目');
            }
            if (isset($visited[$cursor])) {
                break;
            }
            $visited[$cursor] = true;
            $cursor = $parents[$cursor] ?? 0;
        }
    }
}
