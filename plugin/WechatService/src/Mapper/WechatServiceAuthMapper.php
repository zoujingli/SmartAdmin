<?php

declare(strict_types=1);

namespace Plugin\WechatService\Mapper;

use Hyperf\Database\Model\Builder;
use Library\Constants\DataField;
use Library\CoreMapper;
use Plugin\WechatService\Model\WechatServiceAuth;

final class WechatServiceAuthMapper extends CoreMapper
{
    public function __construct(
        protected string $model = WechatServiceAuth::class
    ) {}

    public function getPageList(?array $params, bool $isScope = true, string $pageName = 'page'): array
    {
        // 授权账号是租户共享的微信资源；列表只按 tenant_id 全局范围隔离，不套用户 created_by 数据范围。
        return parent::getPageList($params, false, $pageName);
    }

    public function getDataList(?array $params, bool $isScope = true): array
    {
        return parent::getDataList($params, false);
    }

    public function findByAppid(string $appid): ?WechatServiceAuth
    {
        /** @var null|WechatServiceAuth $authorizer */
        $authorizer = $this->model::query()
            ->withoutGlobalScope(DataField::TENANT)
            ->where('authorizer_appid', $appid)
            ->first();

        return $authorizer;
    }

    public function findAnyByAppid(string $appid): ?WechatServiceAuth
    {
        /** @var null|WechatServiceAuth $authorizer */
        $authorizer = $this->model::withTrashed()
            ->withoutGlobalScope(DataField::TENANT)
            ->where('authorizer_appid', $appid)
            ->first();

        return $authorizer;
    }

    /**
     * 按 AppID 跨租户更新授权账号。
     *
     * 开放平台 Ticket、JSON-RPC Token 刷新等回调没有后台登录态，不能依赖当前协程 tenant_id；
     * 这里仅用于已经通过官方 AppID 定位到的授权账号，写入前仍走 fillable 白名单过滤。
     *
     * @param array<string,mixed> $data
     */
    public function updateByAppid(string $appid, array $data): bool
    {
        $authorizer = $this->findByAppid($appid);
        if (!$authorizer instanceof WechatServiceAuth) {
            return false;
        }

        $payload = $this->filterModelData($data, true);
        if ($payload === []) {
            return true;
        }

        return (bool)$authorizer->update($payload);
    }

    public function incrementTotal(string $appid): void
    {
        $this->model::query()
            ->withoutGlobalScope(DataField::TENANT)
            ->where('authorizer_appid', $appid)
            ->increment('total');
    }

    /**
     * 授权账号读写按 appid 唯一和租户全局范围保护，不按创建人数据范围切分。
     */
    protected function isOperationScopeEnabled(): bool
    {
        return false;
    }

    protected function handleSearch(Builder $query, array $params): Builder
    {
        $query = $this->applyRequestedTenantScope($query, $params);

        return _query($query, $params)
            ->like('nick_name,authorizer_appid,principal_name')
            ->equal('tenant_id,account_type,status')
            ->dateBetween('created_at')
            ->getQuery();
    }
}
