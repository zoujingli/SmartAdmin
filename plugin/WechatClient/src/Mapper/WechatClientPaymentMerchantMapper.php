<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Plugin\WechatClient\Mapper;

use Hyperf\Database\Model\Builder;
use Library\Constants\DataField;
use Library\CoreMapper;
use Plugin\WechatClient\Model\WechatClientPaymentMerchant;

/**
 * 微信支付商户 Mapper。
 *
 * 支付商户是租户级基础配置，后台列表需要支持跨运营范围查看。
 */
final class WechatClientPaymentMerchantMapper extends CoreMapper
{
    public function __construct(
        protected string $model = WechatClientPaymentMerchant::class
    ) {}

    /**
     * 后台商户列表不走操作范围过滤，仅保留租户和查询条件控制。
     */
    public function getPageList(?array $params, bool $isScope = true, string $pageName = 'page'): array
    {
        return parent::getPageList($params, false, $pageName);
    }

    /**
     * 微信支付通知没有后台登录态，只按回调路径中的商户 ID 定位后续租户上下文。
     */
    public function findForCallback(int $id): ?WechatClientPaymentMerchant
    {
        /* @var null|WechatClientPaymentMerchant $merchant */
        return $this->model::query()
            ->withoutGlobalScope(DataField::TENANT)
            ->where('id', $id)
            ->first();
    }

    /**
     * 跨租户检查微信支付商户号是否已存在。
     *
     * 商户号在本地唯一索引约束下不能重复，服务层先给出业务错误，避免暴露数据库异常。
     */
    public function existsByMchId(string $mchId, int $ignoreId = 0): bool
    {
        $query = $this->model::withTrashed()
            ->withoutGlobalScope(DataField::TENANT)
            ->where('mch_id', $mchId);
        if ($ignoreId > 0) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    /**
     * 禁用操作范围过滤，避免支付商户被后台用户数据权限误过滤。
     */
    protected function isOperationScopeEnabled(): bool
    {
        return false;
    }

    /**
     * 处理后台商户列表查询条件。
     */
    protected function handleSearch(Builder $query, array $params): Builder
    {
        return _query($query, $params)
            ->like('name,appid,mch_id')
            ->equal('tenant_id,account_id,status')
            ->dateBetween('created_at')
            ->getQuery();
    }
}
