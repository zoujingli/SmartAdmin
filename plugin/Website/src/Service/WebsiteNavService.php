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
use Plugin\Website\Mapper\WebsiteNavMapper;
use Plugin\Website\Model\WebsiteContent;
use Plugin\Website\Model\WebsiteNav;
use Plugin\Website\Service\Concerns\WebsiteServiceHelpers;
use Plugin\Website\Support\WebsiteData;

/**
 * 官网导航服务。
 */
final class WebsiteNavService extends CoreService
{
    use WebsiteServiceHelpers;

    private const LINK_TYPES = ['route', 'url', 'channel', 'content'];

    private const TARGETS = ['self', 'blank'];

    public function __construct(
        protected WebsiteNavMapper $mapper
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
        $this->trimStringFields($data, ['position', 'title', 'link_type', 'route', 'url', 'target']);
        $this->normalizeRouteField($data);
        foreach (['position', 'link_type', 'target'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = strtolower((string)$data[$field]);
            }
        }

        $rules = [
            'site_id.integer' => '所属站点必须为数字',
            'site_id.min:1' => '请选择所属站点',
            'parent_id.integer' => '父级导航必须为数字',
            'parent_id.min:0' => '父级导航不能小于 0',
            'position.filled' => '导航位置不能为空',
            'position.max:30' => '导航位置最多 30 位',
            'title.filled' => '导航标题不能为空',
            'title.max:120' => '导航标题最多 120 位',
            'link_type.filled' => '链接类型不能为空',
            'link_type.in:route,url,channel,content' => '链接类型错误',
            'route.max:255' => '站内路由最多 255 位',
            'url.max:500' => '外部地址最多 500 位',
            'channel_id.integer' => '关联栏目必须为数字',
            'channel_id.min:0' => '关联栏目不能小于 0',
            'content_id.integer' => '关联内容必须为数字',
            'content_id.min:0' => '关联内容不能小于 0',
            'target.in:self,blank' => '打开方式错误',
            'sort.integer' => '排序必须为数字',
            'status.integer' => '状态必须为数字',
            'status.in:1,0' => '状态值错误',
        ];
        if ($exists === []) {
            $rules['site_id.required'] = '请选择所属站点';
            $rules['position.default'] = 'top';
            $rules['title.required'] = '导航标题不能为空';
            $rules['link_type.default'] = 'route';
            $rules['parent_id.default'] = 0;
            $rules['channel_id.default'] = 0;
            $rules['content_id.default'] = 0;
            $rules['target.default'] = 'self';
            $rules['sort.default'] = 0;
            $rules['status.default'] = Status::ENABLED;
        }

        $data = _vali($rules, $data);
        $this->normalizeIntFields($data, ['site_id', 'parent_id', 'channel_id', 'content_id', 'sort', 'status']);
        $siteId = (int)($data['site_id'] ?? $exists['site_id'] ?? 0);
        $site = $this->ensureSite($siteId);
        // 导航租户归属必须与站点一致，避免前端切站或恶意请求造成跨租户导航数据。
        $data['tenant_id'] = (int)$site->tenant_id;
        $parentId = (int)($data['parent_id'] ?? $exists['parent_id'] ?? 0);
        if ($parentId > 0) {
            if ($exists !== [] && $parentId === (int)($exists['id'] ?? 0)) {
                throw new ErrorResponseException('父级导航不能选择自身');
            }
            if ($exists !== []) {
                $this->assertParentDoesNotCreateCycle((int)($exists['id'] ?? 0), $parentId, $siteId);
            }
            $parent = WebsiteNav::query()->where('id', $parentId)->where('site_id', $siteId)->first();
            if (!$parent instanceof WebsiteNav) {
                throw new ErrorResponseException('父级导航不存在或不属于当前站点');
            }
        }

        $linkType = (string)($data['link_type'] ?? $exists['link_type'] ?? 'route');
        if (!in_array($linkType, self::LINK_TYPES, true)) {
            throw new ErrorResponseException('链接类型错误');
        }
        if ($linkType === 'channel') {
            $channel = $this->ensureChannel((int)($data['channel_id'] ?? $exists['channel_id'] ?? 0), $siteId, '关联栏目不存在或不属于当前站点');
            $data['route'] = (string)($channel?->route ?? $data['route'] ?? '');
        } elseif ($linkType === 'content') {
            $contentId = (int)($data['content_id'] ?? $exists['content_id'] ?? 0);
            $content = $contentId > 0 ? WebsiteContent::query()->where('id', $contentId)->where('site_id', $siteId)->first() : null;
            if (!$content instanceof WebsiteContent) {
                throw new ErrorResponseException('关联内容不存在或不属于当前站点');
            }
            $data['route'] = (string)($content->route ?: ('/website/content/' . (int)$content->id . '/'));
        } elseif ($linkType === 'url' && trim((string)($data['url'] ?? $exists['url'] ?? '')) === '') {
            throw new ErrorResponseException('外部链接不能为空');
        } elseif ($linkType === 'route' && trim((string)($data['route'] ?? $exists['route'] ?? '')) === '') {
            throw new ErrorResponseException('站内路由不能为空');
        }

        if (array_key_exists('target', $data) && !in_array((string)$data['target'], self::TARGETS, true)) {
            throw new ErrorResponseException('打开方式错误');
        }

        return $data;
    }

    private function assertParentDoesNotCreateCycle(int $currentId, int $parentId, int $siteId): void
    {
        if ($currentId <= 0 || $parentId <= 0) {
            return;
        }

        // 导航树与栏目树一样禁止闭环，否则公开 nav/tree 会出现节点丢失或递归异常。
        $parents = [];
        foreach (WebsiteNav::query()->where('site_id', $siteId)->get(['id', 'parent_id']) as $nav) {
            $parents[(int)$nav->id] = (int)$nav->parent_id;
        }

        $visited = [];
        for ($cursor = $parentId; $cursor > 0;) {
            if ($cursor === $currentId) {
                throw new ErrorResponseException('父级导航不能选择当前导航的下级导航');
            }
            if (isset($visited[$cursor])) {
                break;
            }
            $visited[$cursor] = true;
            $cursor = $parents[$cursor] ?? 0;
        }
    }
}
