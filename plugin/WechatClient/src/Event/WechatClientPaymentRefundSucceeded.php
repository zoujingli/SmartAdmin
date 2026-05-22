<?php

declare(strict_types=1);

namespace Plugin\WechatClient\Event;

use Plugin\WechatClient\Model\WechatClientPaymentRefund;

/**
 * 微信退款成功事件。
 *
 * 仅在本地退款状态首次进入 SUCCESS 时派发，业务模块可在监听器中幂等更新售后或订单状态。
 */
final class WechatClientPaymentRefundSucceeded
{
    /**
     * @param array<string,mixed> $payload 微信退款通知或查单返回的标准化数据
     */
    public function __construct(
        public readonly WechatClientPaymentRefund $refund,
        public readonly array $payload = [],
    ) {}
}
