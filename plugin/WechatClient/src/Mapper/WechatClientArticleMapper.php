<?php

declare(strict_types=1);

namespace Plugin\WechatClient\Mapper;

use Hyperf\Database\Model\Builder;
use Library\CoreMapper;
use Plugin\WechatClient\Model\WechatClientArticle;

/**
 * 微信图文文章 Mapper。
 *
 * 文章是公众号账号级素材资源，列表与读写只按租户和账号边界控制。
 */
final class WechatClientArticleMapper extends CoreMapper
{
    public function __construct(
        protected string $model = WechatClientArticle::class
    ) {}

    public function getPageList(?array $params, bool $isScope = true, string $pageName = 'page'): array
    {
        return parent::getPageList($params, false, $pageName);
    }

    /**
     * 禁用创建人数据范围，避免多人维护同一公众号文章时互相不可见。
     */
    protected function isOperationScopeEnabled(): bool
    {
        return false;
    }

    /**
     * 处理文章列表筛选条件。
     */
    protected function handleSearch(Builder $query, array $params): Builder
    {
        return _query($query, $params)
            ->like('title,author,draft_media_id,publish_id')
            ->equal('tenant_id,account_id,publish_status,status')
            ->dateBetween('created_at')
            ->getQuery();
    }
}
