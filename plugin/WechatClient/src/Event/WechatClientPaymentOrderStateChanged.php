<?php

declare(strict_types=1);

namespace Plugin\WechatClient\Event;

use Plugin\WechatClient\Model\WechatClientPaymentOrder;

/**
 * 微信支付状态变化事件。
 *
 * 支付通知或主动查单导致 trade_state 变化时派发，用于业务模块记录状态轨迹或做补偿处理。
 */
final class WechatClientPaymentOrderStateChanged
{
    /**
     * @param array<string,mixed> $payload 微信支付通知或查单返回的标准化数据
     */
    public function __construct(
        public readonly WechatClientPaymentOrder $order,
        public readonly string $oldState,
        public readonly string $newState,
        public readonly array $payload = [],
    ) {}
}
