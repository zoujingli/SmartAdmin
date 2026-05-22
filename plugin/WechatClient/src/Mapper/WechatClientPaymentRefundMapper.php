<?php

declare(strict_types=1);

namespace Plugin\WechatClient\Mapper;

use Hyperf\Database\Model\Builder;
use Library\CoreMapper;
use Plugin\WechatClient\Model\WechatClientPaymentRefund;

/**
 * 微信退款 Mapper。
 *
 * 提供后台退款记录筛选能力，退款状态推进由服务层负责。
 */
final class WechatClientPaymentRefundMapper extends CoreMapper
{
    public function __construct(
        protected string $model = WechatClientPaymentRefund::class
    ) {}

    /**
     * 退款列表按租户和筛选条件查询，不叠加操作范围过滤。
     */
    public function getPageList(?array $params, bool $isScope = true, string $pageName = 'page'): array
    {
        return parent::getPageList($params, false, $pageName);
    }

    /**
     * 禁用操作范围过滤，避免后台用户权限影响退款流水排查。
     */
    protected function isOperationScopeEnabled(): bool
    {
        return false;
    }

    /**
     * 处理后台退款列表查询条件。
     */
    protected function handleSearch(Builder $query, array $params): Builder
    {
        return _query($query, $params)
            ->like('order_no,out_trade_no,out_refund_no,refund_id,reason')
            ->equal('tenant_id,merchant_id,order_id,refund_status,status')
            ->dateBetween('created_at')
            ->getQuery();
    }
}
