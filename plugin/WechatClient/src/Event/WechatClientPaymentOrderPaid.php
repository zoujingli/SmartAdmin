<?php

declare(strict_types=1);

namespace Plugin\WechatClient\Event;

use Plugin\WechatClient\Model\WechatClientPaymentOrder;

/**
 * 微信支付成功事件。
 *
 * 仅在本地支付状态首次进入 SUCCESS 时派发，业务模块可在监听器中幂等更新订单状态。
 */
final class WechatClientPaymentOrderPaid
{
    /**
     * @param array<string,mixed> $payload 微信支付通知或查单返回的标准化数据
     */
    public function __construct(
        public readonly WechatClientPaymentOrder $order,
        public readonly array $payload = [],
    ) {}
}
