<?php

declare(strict_types=1);

namespace Plugin\WechatClient\Mapper;

use Hyperf\Database\Model\Builder;
use Library\CoreMapper;
use Plugin\WechatClient\Model\WechatClientPaymentOrder;

/**
 * 微信支付订单 Mapper。
 *
 * 提供后台列表筛选能力，支付状态推进由服务层负责。
 */
final class WechatClientPaymentOrderMapper extends CoreMapper
{
    public function __construct(
        protected string $model = WechatClientPaymentOrder::class
    ) {}

    /**
     * 支付订单列表按租户和筛选条件查询，不叠加操作范围过滤。
     */
    public function getPageList(?array $params, bool $isScope = true, string $pageName = 'page'): array
    {
        return parent::getPageList($params, false, $pageName);
    }

    /**
     * 禁用操作范围过滤，避免后台用户权限影响支付流水排查。
     */
    protected function isOperationScopeEnabled(): bool
    {
        return false;
    }

    /**
     * 处理后台支付订单列表查询条件。
     */
    protected function handleSearch(Builder $query, array $params): Builder
    {
        return _query($query, $params)
            ->like('order_no,out_trade_no,transaction_id,description,payer_openid')
            ->equal('tenant_id,merchant_id,appid,mch_id,trade_type,trade_state,status')
            ->dateBetween('created_at')
            ->getQuery();
    }
}
