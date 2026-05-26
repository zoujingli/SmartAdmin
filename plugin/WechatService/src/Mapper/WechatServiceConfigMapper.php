<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Plugin\WechatService\Mapper;

use Hyperf\Database\Model\Builder;
use Library\CoreMapper;
use Plugin\WechatService\Model\WechatServiceConfig;

final class WechatServiceConfigMapper extends CoreMapper
{
    public function __construct(
        protected string $model = WechatServiceConfig::class
    ) {}

    public function getPageList(?array $params, bool $isScope = true, string $pageName = 'page'): array
    {
        return parent::getPageList($params, false, $pageName);
    }

    public function getDataList(?array $params, bool $isScope = true): array
    {
        return parent::getDataList($params, false);
    }

    public function active(): ?WechatServiceConfig
    {
        /* @var null|WechatServiceConfig $config */
        return $this->model::query()->orderBy('id')->first();
    }

    /**
     * 检查第三方平台 AppID 是否已存在，包含软删除配置，避免唯一索引异常泄露给前端。
     */
    public function existsByComponentAppid(string $appid, int $ignoreId = 0): bool
    {
        $query = $this->model::withTrashed()->where('component_appid', $appid);
        if ($ignoreId > 0) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    /**
     * 开放平台配置是平台级资源，权限由 wechat.service.config.* 控制，不按创建人切分。
     */
    protected function isOperationScopeEnabled(): bool
    {
        return false;
    }

    protected function handleSearch(Builder $query, array $params): Builder
    {
        return _query($query, $params)
            ->like('name,component_appid')
            ->equal('status')
            ->dateBetween('created_at')
            ->getQuery();
    }
}
