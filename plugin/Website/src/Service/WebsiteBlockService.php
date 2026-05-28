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
use Plugin\Website\Mapper\WebsiteBlockMapper;
use Plugin\Website\Model\WebsiteBlock;
use Plugin\Website\Service\Concerns\WebsiteServiceHelpers;
use Plugin\Website\Support\WebsiteData;
use Plugin\Website\Support\WebsitePublishStatus;

/**
 * 官网页面区块服务。
 */
final class WebsiteBlockService extends CoreService
{
    use WebsiteServiceHelpers;

    public function __construct(
        protected WebsiteBlockMapper $mapper
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function options(array $params = []): array
    {
        return $this->mapper->options($params);
    }

    protected function filterData(array &$data, array $exists = []): array
    {
        $this->trimStringFields($data, ['page_code', 'group_code', 'code', 'name', 'type', 'title', 'subtitle', 'publish_status']);
        foreach (['page_code', 'group_code', 'code', 'type'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = strtolower((string)$data[$field]);
            }
        }
        $this->normalizeDateTimeFields($data, ['published_at', 'offline_at']);

        $rules = [
            'site_id.integer' => '所属站点必须为数字',
            'site_id.min:1' => '请选择所属站点',
            'page_code.filled' => '页面编码不能为空',
            'page_code.max:80' => '页面编码最多 80 位',
            'group_code.max:80' => '分组编码最多 80 位',
            'code.filled' => '区块编码不能为空',
            'code.max:80' => '区块编码最多 80 位',
            'name.filled' => '区块名称不能为空',
            'name.max:120' => '区块名称最多 120 位',
            'type.max:30' => '区块类型最多 30 位',
            'title.max:180' => '区块标题最多 180 位',
            'subtitle.max:500' => '区块副标题最多 500 位',
            'payload.nullable' => '区块数据格式错误',
            'media.nullable' => '媒体数据格式错误',
            'link.nullable' => '链接数据格式错误',
            'sort.integer' => '排序必须为数字',
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
            $rules['page_code.required'] = '页面编码不能为空';
            $rules['code.required'] = '区块编码不能为空';
            $rules['name.required'] = '区块名称不能为空';
            $rules['group_code.default'] = 'main';
            $rules['type.default'] = 'custom';
            $rules['payload.default'] = [];
            $rules['media.default'] = [];
            $rules['link.default'] = [];
            $rules['sort.default'] = 0;
            $rules['publish_status.default'] = WebsitePublishStatus::DRAFT;
            $rules['status.default'] = Status::ENABLED;
        }

        $data = _vali($rules, $data);
        $this->normalizeIntFields($data, ['site_id', 'sort', 'status']);
        $siteId = (int)($data['site_id'] ?? $exists['site_id'] ?? 0);
        $this->ensureSite($siteId);
        foreach (['payload', 'media', 'link'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = WebsiteData::object($data[$field]);
            }
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
        $this->ensureUniquePageBlock($data, $exists);

        return $data;
    }

    private function ensureUniquePageBlock(array $data, array $exists): void
    {
        $code = trim((string)($data['code'] ?? $exists['code'] ?? ''));
        $pageCode = trim((string)($data['page_code'] ?? $exists['page_code'] ?? ''));
        $siteId = (int)($data['site_id'] ?? $exists['site_id'] ?? 0);
        if ($code === '' || $pageCode === '' || $siteId <= 0) {
            return;
        }

        $query = WebsiteBlock::query()->where('site_id', $siteId)->where('page_code', $pageCode)->where('code', $code);
        if (!empty($exists['id'])) {
            $query->where('id', '!=', (int)$exists['id']);
        }
        if ($query->exists()) {
            throw new ErrorResponseException('当前页面下区块编码已存在');
        }
    }
}
