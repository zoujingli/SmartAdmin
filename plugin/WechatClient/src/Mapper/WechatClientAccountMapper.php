<?php

declare(strict_types=1);

namespace Plugin\WechatClient\Mapper;

use Hyperf\Database\Model\Builder;
use Library\Constants\DataField;
use Library\CoreMapper;
use Plugin\WechatClient\Model\WechatClientAccount;

/**
 * 微信接口账号 Mapper。
 *
 * 提供后台账号列表筛选能力，并支持微信回调按 AppID 跨租户定位账号。
 */
final class WechatClientAccountMapper extends CoreMapper
{
    public function __construct(
        protected string $model = WechatClientAccount::class
    ) {}

    /**
     * 账号列表不走操作范围过滤，仅按租户和筛选条件控制。
     */
    public function getPageList(?array $params, bool $isScope = true, string $pageName = 'page'): array
    {
        return parent::getPageList($params, false, $pageName);
    }

    /**
     * 回调场景按 AppID 跨租户查找账号。
     */
    public function findByAppid(string $appid): ?WechatClientAccount
    {
        /** @var null|WechatClientAccount $account */
        $account = $this->model::query()
            ->withoutGlobalScope(DataField::TENANT)
            ->where('appid', $appid)
            ->first();

        return $account;
    }

    /**
     * 跨租户检查 AppID 是否已被绑定。
     *
     * 微信 AppID 在全平台唯一，创建前必须绕过当前租户范围检查，避免最终落到数据库唯一索引异常。
     */
    public function existsByAppid(string $appid, int $ignoreId = 0): bool
    {
        $query = $this->model::withTrashed()
            ->withoutGlobalScope(DataField::TENANT)
            ->where('appid', $appid);
        if ($ignoreId > 0) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    /**
     * 禁用操作范围过滤，避免后台用户权限影响接口账号管理。
     */
    protected function isOperationScopeEnabled(): bool
    {
        return false;
    }

    /**
     * 处理后台账号列表查询条件。
     */
    protected function handleSearch(Builder $query, array $params): Builder
    {
        $query = $this->applyRequestedTenantScope($query, $params);

        return _query($query, $params)
            ->like('name,appid')
            ->equal('tenant_id,account_type,service_mode,status')
            ->dateBetween('created_at')
            ->getQuery();
    }
}
