<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://doc.hyperf.thinkadmin.top
 */

namespace Plugin\WechatClient\Service;

use Plugin\WechatClient\Model\WechatClientPaymentRefund;

/**
 * 面向业务模块的微信支付门面服务。
 *
 * 业务模块优先依赖此类创建支付、退款和查询状态，避免直接耦合微信 API 字段。
 */
final class WechatClientPaymentService
{
    public function __construct(
        private readonly WechatClientPaymentOrderService $orders,
        private readonly WechatClientPaymentRefundService $refunds,
    ) {}

    /**
     * 创建订单支付。
     *
     * 业务模块优先调用此方法，避免直接依赖微信下单字段：
     * - $orderNo：业务订单号
     * - $amountTotal：支付金额，单位分
     * - $description：支付描述
     * - $options：merchant_id、trade_type、openid/payer_openid、notify_url、attach、time_expire 等扩展参数
     *
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public function createOrderPayment(string $orderNo, int $amountTotal, string $description, array $options = []): array
    {
        return $this->orders->createPayment(array_merge($options, [
            'order_no' => $orderNo,
            'amount_total' => $amountTotal,
            'description' => $description,
        ]));
    }

    /**
     * 创建订单退款。
     *
     * 业务模块优先调用此方法；默认按业务订单号寻找最近一笔已支付支付单，
     * 如需指定某笔支付，可在 $options 中传 payment_no 或 order_id。
     *
     * @param array<string,mixed> $options
     */
    public function createOrderRefund(string $orderNo, int $amountRefund, string $reason = '', array $options = []): WechatClientPaymentRefund
    {
        return $this->refunds->refund(array_merge($options, [
            'order_no' => $orderNo,
            'amount_refund' => $amountRefund,
            'reason' => $reason,
        ]));
    }

    /**
     * 查询订单支付状态。本地未完成时会自动查微信线上状态并同步本地。
     *
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public function queryOrderPayment(string $orderNo, array $options = []): array
    {
        return $this->orders->queryPayment(array_merge($options, ['order_no' => $orderNo]));
    }

    /**
     * 查询订单退款状态。本地未完成时会自动查微信线上状态并同步本地。
     *
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public function queryOrderRefund(string $orderNo, array $options = []): array
    {
        return $this->refunds->queryRefund(array_merge($options, ['order_no' => $orderNo]));
    }

    /**
     * 发起支付。最小入参：order_no + amount_total(分) + description；JSAPI 需额外传 openid。
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function payment(array $data): array
    {
        return $this->orders->createPayment($data);
    }

    /**
     * 发起退款。最小入参：order_no/payment_no/order_id 其一 + amount_refund(分) + reason。
     *
     * @param array<string,mixed> $data
     */
    public function refund(array $data): WechatClientPaymentRefund
    {
        return $this->refunds->refund($data);
    }

    /**
     * 主动查单补偿；正常业务应优先依赖微信支付通知。
     *
     * @return array<string,mixed>
     */
    public function syncPayment(string $paymentNo, bool $force = false): array
    {
        return $this->orders->syncPayment($paymentNo, $force);
    }

    /**
     * 查询支付状态。本地未完成时会自动查微信线上状态并同步本地。
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function queryPayment(array $data): array
    {
        return $this->orders->queryPayment($data);
    }

    /**
     * 主动查退款补偿；正常业务应优先依赖微信退款通知。
     *
     * @return array<string,mixed>
     */
    public function syncRefund(string $refundNo, bool $force = false): array
    {
        return $this->refunds->syncRefund($refundNo, $force);
    }

    /**
     * 查询退款状态。本地未完成时会自动查微信线上状态并同步本地。
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function queryRefund(array $data): array
    {
        return $this->refunds->queryRefund($data);
    }
}
