<?php

declare(strict_types=1);

namespace Plugin\WechatClient\Event;

use Plugin\WechatClient\Model\WechatClientPaymentRefund;

/**
 * 微信退款状态变化事件。
 *
 * 退款通知或主动查询导致 refund_status 变化时派发，用于业务模块记录退款状态轨迹。
 */
final class WechatClientPaymentRefundStateChanged
{
    /**
     * @param array<string,mixed> $payload 微信退款通知或查单返回的标准化数据
     */
    public function __construct(
        public readonly WechatClientPaymentRefund $refund,
        public readonly string $oldState,
        public readonly string $newState,
        public readonly array $payload = [],
    ) {}
}
