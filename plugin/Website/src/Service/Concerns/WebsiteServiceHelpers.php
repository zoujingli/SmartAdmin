<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Plugin\Website\Service\Concerns;

use Hyperf\Database\Model\Model;
use Library\Exception\ErrorResponseException;
use Plugin\Website\Model\WebsiteChannel;
use Plugin\Website\Model\WebsiteSite;
use Plugin\Website\Support\WebsiteData;

/**
 * 官网 Service 公共校验工具。
 *
 * 只封装跨资源重复出现的站点归属、站内唯一性和基础字段标准化；业务状态流转仍留在各 Service 中，
 * 避免把官网发布规则隐藏到不可见的通用层。
 */
trait WebsiteServiceHelpers
{
    /**
     * @param array<int, string> $fields
     */
    protected function trimStringFields(array &$data, array $fields): void
    {
        foreach ($fields as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }
    }

    /**
     * @param array<int, string> $fields
     */
    protected function normalizeIntFields(array &$data, array $fields): void
    {
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = (int)$data[$field];
            }
        }
    }

    /**
     * @param array<int, string> $fields
     */
    protected function normalizeDateTimeFields(array &$data, array $fields): void
    {
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = WebsiteData::nullableDateTime($data[$field]);
            }
        }
    }

    protected function normalizeRouteField(array &$data, string $field = 'route'): void
    {
        if (array_key_exists($field, $data)) {
            $data[$field] = WebsiteData::route((string)$data[$field]);
        }
    }

    protected function ensureSite(int $siteId, string $message = '站点不存在或无权限选择'): WebsiteSite
    {
        $site = WebsiteSite::query()->where('id', $siteId)->first();
        if (!$site instanceof WebsiteSite) {
            throw new ErrorResponseException($message);
        }

        return $site;
    }

    protected function ensureChannel(int $channelId, int $siteId, string $message = '栏目不存在或不属于当前站点'): ?WebsiteChannel
    {
        if ($channelId <= 0) {
            return null;
        }

        $channel = WebsiteChannel::query()->where('id', $channelId)->where('site_id', $siteId)->first();
        if (!$channel instanceof WebsiteChannel) {
            throw new ErrorResponseException($message);
        }

        return $channel;
    }

    /**
     * @param class-string<Model> $modelClass
     */
    protected function ensureUniqueInSite(string $modelClass, string $field, array $data, array $exists, string $message): void
    {
        if (!array_key_exists($field, $data) || trim((string)($data[$field] ?? '')) === '') {
            return;
        }

        $siteId = (int)($data['site_id'] ?? $exists['site_id'] ?? 0);
        if ($siteId <= 0) {
            return;
        }

        if ($exists !== [] && (string)($exists[$field] ?? '') === (string)$data[$field] && (int)($exists['site_id'] ?? 0) === $siteId) {
            return;
        }

        $query = $modelClass::query()->where('site_id', $siteId)->where($field, $data[$field]);
        if (!empty($exists['id'])) {
            $query->where('id', '!=', (int)$exists['id']);
        }
        if ($query->exists()) {
            throw new ErrorResponseException($message);
        }
    }
}
