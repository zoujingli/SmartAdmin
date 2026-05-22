<?php

declare(strict_types=1);

namespace Plugin\WechatClient\Mapper;

use Hyperf\Database\Model\Builder;
use Library\CoreMapper;
use Plugin\WechatClient\Model\WechatClientMedia;

/**
 * 微信素材 Mapper。
 *
 * 素材属于租户账号资源，后台列表仅按租户、账号和筛选条件控制，不叠加创建人数据范围。
 */
final class WechatClientMediaMapper extends CoreMapper
{
    public function __construct(
        protected string $model = WechatClientMedia::class
    ) {}

    public function getPageList(?array $params, bool $isScope = true, string $pageName = 'page'): array
    {
        return parent::getPageList($params, false, $pageName);
    }

    /**
     * 按微信 MediaID 幂等保存官方素材，避免重复同步产生多条记录。
     *
     * @param array<string,mixed> $data
     */
    public function upsertByMediaId(int $accountId, string $mediaId, array $data): WechatClientMedia
    {
        /** @var WechatClientMedia $media */
        $media = $this->model::query()->updateOrCreate(['account_id' => $accountId, 'media_id' => $mediaId], $data);

        return $media;
    }

    /**
     * 后台素材管理禁用创建人数据范围，仅依赖租户隔离和账号筛选。
     */
    protected function isOperationScopeEnabled(): bool
    {
        return false;
    }

    /**
     * 处理素材列表筛选条件。
     */
    protected function handleSearch(Builder $query, array $params): Builder
    {
        return _query($query, $params)
            ->like('name,media_id,url,file_url')
            ->equal('tenant_id,account_id,media_type,status')
            ->dateBetween('created_at')
            ->getQuery();
    }
}
