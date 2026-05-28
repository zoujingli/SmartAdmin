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
use Plugin\Website\Mapper\WebsiteContentMapper;
use Plugin\Website\Model\WebsiteContent;
use Plugin\Website\Service\Concerns\WebsiteServiceHelpers;
use Plugin\Website\Support\RichText;
use Plugin\Website\Support\WebsiteData;
use Plugin\Website\Support\WebsitePublishStatus;

/**
 * 官网内容服务。
 */
final class WebsiteContentService extends CoreService
{
    use WebsiteServiceHelpers;

    public function __construct(
        protected WebsiteContentMapper $mapper
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function options(array $params = []): array
    {
        return $this->mapper->options($params);
    }

    public function publish(int $id, array $data = []): WebsiteContent
    {
        $model = $this->mapper->read($id);
        if (!$model instanceof WebsiteContent) {
            throw new ErrorResponseException('内容不存在');
        }

        // 发布入口只允许调整发布时间和下线时间；发布时间在未来时进入 scheduled，已到时间则立即 published。
        $dates = [];
        if (array_key_exists('published_at', $data)) {
            $dates['published_at'] = WebsiteData::nullableDateTime($data['published_at']);
        }
        if (array_key_exists('offline_at', $data)) {
            $dates['offline_at'] = WebsiteData::nullableDateTime($data['offline_at']);
        }
        $dates = _vali([
            'published_at.nullable' => '发布时间格式错误',
            'published_at.date' => '发布时间格式错误',
            'offline_at.nullable' => '下线时间格式错误',
            'offline_at.date' => '下线时间格式错误',
        ], $dates);

        $publishedAt = $dates['published_at'] ?? date('Y-m-d H:i:s');
        $offlineAt = $dates['offline_at'] ?? WebsiteData::nullableDateTime($model->offline_at);
        if ($offlineAt !== null && $offlineAt <= $publishedAt) {
            throw new ErrorResponseException('下线时间必须晚于发布时间');
        }
        $payload = [
            'publish_status' => $publishedAt > date('Y-m-d H:i:s') ? WebsitePublishStatus::SCHEDULED : WebsitePublishStatus::PUBLISHED,
            'published_at' => $publishedAt,
            'offline_at' => $offlineAt,
            'status' => Status::ENABLED,
        ];
        $this->mapper->update($model, $payload);

        return $this->mapper->read($id) ?: $model;
    }

    public function offline(int $id): WebsiteContent
    {
        $model = $this->mapper->read($id);
        if (!$model instanceof WebsiteContent) {
            throw new ErrorResponseException('内容不存在');
        }

        $this->mapper->update($model, [
            'publish_status' => WebsitePublishStatus::OFFLINE,
            'offline_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->mapper->read($id) ?: $model;
    }

    protected function filterData(array &$data, array $exists = []): array
    {
        $this->trimStringFields($data, ['type', 'title', 'slug', 'route', 'summary', 'cover', 'content_html', 'publish_status']);
        if (array_key_exists('type', $data)) {
            $data['type'] = strtolower((string)$data['type']);
        }
        if (array_key_exists('slug', $data)) {
            $data['slug'] = strtolower((string)$data['slug']);
        }
        if (array_key_exists('route', $data) && trim((string)$data['route']) !== '') {
            $this->normalizeRouteField($data);
        }
        $this->normalizeDateTimeFields($data, ['published_at', 'offline_at']);

        $rules = [
            'site_id.integer' => '所属站点必须为数字',
            'site_id.min:1' => '请选择所属站点',
            'channel_id.integer' => '所属栏目必须为数字',
            'channel_id.min:0' => '所属栏目不能小于 0',
            'type.filled' => '内容类型不能为空',
            'type.max:30' => '内容类型最多 30 位',
            'title.filled' => '内容标题不能为空',
            'title.max:180' => '内容标题最多 180 位',
            'slug.max:160' => '访问标识最多 160 位',
            'route.max:255' => '访问路由最多 255 位',
            'summary.max:1000' => '内容摘要最多 1000 位',
            'cover.max:500' => '封面地址最多 500 位',
            'content_html.max:50000' => '正文内容最多 50000 位',
            'payload.nullable' => '扩展数据格式错误',
            'tags.nullable' => '标签格式错误',
            'seo.nullable' => 'SEO 配置格式错误',
            'sort.integer' => '排序必须为数字',
            'is_top.integer' => '置顶状态必须为数字',
            'is_top.in:1,0' => '置顶状态错误',
            'publish_status.in:draft,scheduled,published,offline' => '发布状态错误',
            'published_at.nullable' => '发布时间格式错误',
            'published_at.date' => '发布时间格式错误',
            'offline_at.nullable' => '下线时间格式错误',
            'offline_at.date' => '下线时间格式错误',
            'status.integer' => '状态必须为数字',
            'status.in:1,0' => '状态值错误',
        ];
        if ($exists === []) {
            $rules['site_id.required'] = '请选择所属站点';
            $rules['type.default'] = 'article';
            $rules['title.required'] = '内容标题不能为空';
            $rules['channel_id.default'] = 0;
            $rules['payload.default'] = [];
            $rules['tags.default'] = [];
            $rules['seo.default'] = [];
            $rules['sort.default'] = 0;
            $rules['is_top.default'] = 0;
            $rules['publish_status.default'] = WebsitePublishStatus::DRAFT;
            $rules['status.default'] = Status::ENABLED;
        }

        $data = _vali($rules, $data);
        $this->normalizeIntFields($data, ['site_id', 'channel_id', 'sort', 'is_top', 'status']);
        $siteId = (int)($data['site_id'] ?? $exists['site_id'] ?? 0);
        $site = $this->ensureSite($siteId);
        // 内容按站点继承租户边界；公开接口后续也依赖 tenant_id + site_id 双条件过滤。
        $data['tenant_id'] = (int)$site->tenant_id;
        $this->ensureChannel((int)($data['channel_id'] ?? $exists['channel_id'] ?? 0), $siteId);
        if (array_key_exists('content_html', $data)) {
            $data['content_html'] = RichText::sanitize((string)$data['content_html']);
        }
        foreach (['payload', 'seo'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = WebsiteData::object($data[$field]);
            }
        }
        if (array_key_exists('tags', $data)) {
            $data['tags'] = WebsiteData::stringList($data['tags']);
        }
        if (array_key_exists('publish_status', $data) && !WebsitePublishStatus::isValid((string)$data['publish_status'])) {
            throw new ErrorResponseException('发布状态错误');
        }
        if (($data['publish_status'] ?? '') === WebsitePublishStatus::PUBLISHED && empty($data['published_at']) && empty($exists['published_at'])) {
            $data['published_at'] = date('Y-m-d H:i:s');
        }
        if (($data['publish_status'] ?? '') === WebsitePublishStatus::SCHEDULED && empty($data['published_at']) && empty($exists['published_at'])) {
            throw new ErrorResponseException('定时发布必须填写发布时间');
        }
        $publishedAt = $data['published_at'] ?? $exists['published_at'] ?? null;
        $offlineAt = $data['offline_at'] ?? $exists['offline_at'] ?? null;
        if ($publishedAt !== null && $offlineAt !== null && (string)$offlineAt <= (string)$publishedAt) {
            throw new ErrorResponseException('下线时间必须晚于发布时间');
        }
        $this->ensureUniqueInSite(WebsiteContent::class, 'slug', $data, $exists, '当前站点下访问标识已存在');
        $this->ensureUniqueInSite(WebsiteContent::class, 'route', $data, $exists, '当前站点下访问路由已存在');

        return $data;
    }
}
