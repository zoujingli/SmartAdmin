<?php

declare(strict_types=1);

namespace Plugin\WechatClient\Mapper;

use Hyperf\Database\Model\Builder;
use Library\Constants\DataField;
use Library\CoreMapper;
use Plugin\WechatClient\Model\WechatClientUser;

/**
 * 微信粉丝/用户 Mapper。
 *
 * 提供粉丝列表筛选能力，并支持按 appid + openid 幂等写入本地用户资料。
 */
final class WechatClientUserMapper extends CoreMapper
{
    public function __construct(
        protected string $model = WechatClientUser::class
    ) {}

    /**
     * 粉丝列表不走操作范围过滤，仅按租户、账号和筛选条件控制。
     */
    public function getPageList(?array $params, bool $isScope = true, string $pageName = 'page'): array
    {
        return parent::getPageList($params, false, $pageName);
    }

    /**
     * 按 appid + openid 幂等创建或更新本地用户资料。
     *
     * @param array<string,mixed> $data
     */
    public function upsertByOpenid(string $appid, string $openid, array $data): WechatClientUser
    {
        /** @var null|WechatClientUser $user */
        $user = $this->model::withTrashed()
            ->withoutGlobalScope(DataField::TENANT)
            ->where('appid', $appid)
            ->where('openid', $openid)
            ->first();
        if ($user instanceof WechatClientUser) {
            // appid + openid 是全局唯一键，回调/同步时需跨租户定位旧记录并按当前账号租户修正，避免唯一索引冲突。
            if (method_exists($user, 'trashed') && $user->trashed()) {
                $user->restore();
            }
            $user->fill($data);
            $user->save();

            return $user;
        }

        /** @var WechatClientUser $user */
        $user = $this->model::query()->create($data);

        return $user;
    }

    /**
     * 禁用操作范围过滤，避免后台用户权限影响粉丝数据同步和排查。
     */
    protected function isOperationScopeEnabled(): bool
    {
        return false;
    }

    /**
     * 处理后台粉丝列表查询条件。
     */
    protected function handleSearch(Builder $query, array $params): Builder
    {
        return _query($query, $params)
            ->like('nickname,openid,unionid')
            ->equal('tenant_id,account_id,appid,subscribe,status')
            ->dateBetween('created_at')
            ->getQuery();
    }
}
