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

use Hyperf\DbConnection\Db;
use Library\Constants\System;
use Library\CoreService;
use Library\Exception\ErrorResponseException;
use Plugin\WechatClient\Event\WechatClientPaymentRefundStateChanged;
use Plugin\WechatClient\Event\WechatClientPaymentRefundSucceeded;
use Plugin\WechatClient\Mapper\WechatClientPaymentRefundMapper;
use Plugin\WechatClient\Model\WechatClientPaymentMerchant;
use Plugin\WechatClient\Model\WechatClientPaymentOrder;
use Plugin\WechatClient\Model\WechatClientPaymentRefund;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * 微信退款服务。
 *
 * 负责本地退款记录创建、微信退款申请、退款通知同步、主动查退款补偿和退款事件派发。
 */
final class WechatClientPaymentRefundService extends CoreService
{
    public function __construct(
        protected WechatClientPaymentRefundMapper $mapper,
        private readonly WechatClientPaymentMerchantService $merchants,
        private readonly WechatClientPaymentOrderService $orders,
        private readonly EventDispatcherInterface $events,
    ) {}

    /**
     * 发起微信退款。
     *
     * 可通过 order_id、payment_no/out_trade_no 或 order_no 定位原支付单；默认按业务订单号
     * 选择最近一笔已支付订单。退款号规则为业务订单号 + 三位递增序号。
     *
     * @param array<string,mixed> $data
     */
    public function refund(array $data): WechatClientPaymentRefund
    {
        $order = $this->resolveOrder($data);
        if (!$order instanceof WechatClientPaymentOrder) {
            throw new ErrorResponseException('支付订单不存在');
        }
        if ((string)$order->trade_state !== 'SUCCESS') {
            throw new ErrorResponseException('仅支付成功的订单可以退款');
        }

        $merchant = $this->merchants->requireMerchant((int)$order->merchant_id);
        $amountRefund = (int)($data['amount_refund'] ?? 0);
        if ($amountRefund <= 0 || $amountRefund > (int)$order->amount_total) {
            throw new ErrorResponseException('退款金额必须大于 0 且不能超过订单金额');
        }
        $notifyUrl = $this->orders->resolveNotifyUrl($merchant, 'refund', (string)($data['notify_url'] ?? ''));
        [$refund, $outRefundNo, $payload] = $this->createLocalRefund($order, $merchant, $amountRefund, $notifyUrl, (string)($data['reason'] ?? ''));

        try {
            $result = $this->merchants->paymentRequest($merchant, 'refund', $payload);
        } catch (\Throwable $exception) {
            // 微信退款申请异常时标记为 FAIL，并保留请求上下文和失败原因。
            $refund->update([
                'refund_status' => 'FAIL',
                'fail_reason' => $exception->getMessage(),
                'raw_payload' => [
                    'request' => $payload,
                    'error' => $exception->getMessage(),
                ],
            ]);
            throw $exception;
        }

        $result['out_refund_no'] = $outRefundNo;
        $result['amount_refund'] = $amountRefund;

        return $this->applyRefundData($refund, $result);
    }

    /**
     * 处理微信支付 APIv3 退款通知；验签和资源解密由 SDK 完成，本地按商户退款号幂等更新。
     *
     * @param array<string,string> $headers
     * @param string $rawBody 微信原始 JSON body，必须用于 APIv3 签名验证
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    public function handleNotification(int $merchantId, array $headers, string $rawBody, array $body): array
    {
        $merchant = $this->merchants->requireMerchantForCallback($merchantId);
        // 退款通知同样没有登录态，先恢复商户所属租户，后续事件可直接感知租户上下文。
        System::setTenantId((int)$merchant->tenant_id);
        $data = $this->merchants->paymentRequest($merchant, 'decrypt_notification', [], 'POST', [
            'headers' => $headers,
            'raw_body' => $rawBody,
            'body' => $body,
        ]);
        $amount = is_array($data['amount'] ?? null) ? $data['amount'] : [];
        $data['amount_refund'] = (int)($amount['refund'] ?? 0);
        $this->assertNotificationMerchant($merchant, $data);

        $refund = $this->findByRefundNo((string)($data['out_refund_no'] ?? ''));
        if (!$refund instanceof WechatClientPaymentRefund) {
            throw new ErrorResponseException('退款记录不存在');
        }
        if ((int)$refund->merchant_id !== (int)$merchant->id) {
            throw new ErrorResponseException('退款通知商户与本地记录不一致');
        }

        $this->applyRefundData($refund, $data);

        return $data;
    }

    /**
     * 按商户退款号查找本地退款记录。
     */
    public function findByRefundNo(string $refundNo): ?WechatClientPaymentRefund
    {
        $refundNo = trim($refundNo);
        if ($refundNo === '') {
            return null;
        }

        return WechatClientPaymentRefund::query()->where('out_refund_no', $refundNo)->first();
    }

    /**
     * 查询退款状态：本地已终态直接返回，本地未完成时查询微信并同步后返回。
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function queryRefund(array $data): array
    {
        $refund = $this->resolveRefund($data);
        if (!$refund instanceof WechatClientPaymentRefund) {
            throw new ErrorResponseException('退款记录不存在');
        }

        $onlineQueried = false;
        $onlineResult = [];
        if (!$this->isRefundFinished((string)$refund->refund_status)) {
            $onlineQueried = true;
            $onlineResult = $this->syncRefund((string)$refund->out_refund_no, true);
        }

        $fresh = $this->findByRefundNo((string)$refund->out_refund_no) ?? $refund;

        return [
            'refund' => $fresh,
            'order_no' => (string)$fresh->order_no,
            'payment_no' => (string)$fresh->out_trade_no,
            'refund_no' => (string)$fresh->out_refund_no,
            'refund_status' => (string)$fresh->refund_status,
            'local_finished' => $this->isRefundFinished((string)$fresh->refund_status),
            'online_queried' => $onlineQueried,
            'online' => $onlineResult,
        ];
    }

    /**
     * 主动查退款补偿。业务应优先依赖微信通知；此方法用于通知丢失或人工排查。
     *
     * $force=false 时，本地已经进入终态会直接返回，避免重复访问微信接口。
     *
     * @return array<string,mixed>
     */
    public function syncRefund(string $refundNo, bool $force = false): array
    {
        $refund = $this->findByRefundNo($refundNo);
        if (!$refund instanceof WechatClientPaymentRefund) {
            throw new ErrorResponseException('退款记录不存在');
        }
        if (!$force && in_array((string)$refund->refund_status, ['SUCCESS', 'CLOSED', 'ABNORMAL', 'FAIL'], true)) {
            return ['refund_status' => (string)$refund->refund_status];
        }

        $merchant = $this->merchants->requireMerchant((int)$refund->merchant_id);
        $result = $this->merchants->paymentRequest(
            $merchant,
            'v3/refund/domestic/refunds/' . rawurlencode($refundNo),
            [],
            'GET',
        );
        $result['out_refund_no'] = $refundNo;
        $this->applyRefundData($refund, $result);

        return $result;
    }

    /**
     * 应用微信退款通知或查单结果到本地退款记录。
     *
     * 该方法是退款状态推进的唯一入口：会回填微信退款单号、退款金额、完成时间和失败原因，
     * 并在状态变化或退款成功时派发内部事件。
     *
     * @param array<string,mixed> $data
     */
    public function applyRefundData(WechatClientPaymentRefund $refund, array $data): WechatClientPaymentRefund
    {
        $oldState = (string)$refund->refund_status;
        $newState = trim((string)($data['refund_status'] ?? $data['status'] ?? $oldState));
        if ($newState === '') {
            $newState = $oldState;
        }
        $amount = is_array($data['amount'] ?? null) ? $data['amount'] : [];
        $outTradeNo = trim((string)($data['out_trade_no'] ?? ''));
        if ($outTradeNo !== '' && trim((string)$refund->out_trade_no) !== '' && $outTradeNo !== (string)$refund->out_trade_no) {
            throw new ErrorResponseException('退款通知支付号与本地记录不一致');
        }
        $amountTotal = (int)($data['amount_total'] ?? $amount['total'] ?? 0);
        if ($amountTotal > 0 && (int)$refund->amount_total > 0 && $amountTotal !== (int)$refund->amount_total) {
            throw new ErrorResponseException('退款通知订单金额与本地订单金额不一致');
        }
        $amountRefund = (int)($data['amount_refund'] ?? $amount['refund'] ?? 0);
        if ($amountRefund > 0 && (int)$refund->amount_refund > 0 && $amountRefund !== (int)$refund->amount_refund) {
            throw new ErrorResponseException('退款通知金额与本地退款金额不一致');
        }
        $refundedAt = $newState === 'SUCCESS' ? $this->normalizeWechatTime((string)($data['success_time'] ?? '')) : null;
        $failReason = in_array($newState, ['CLOSED', 'ABNORMAL', 'FAIL'], true)
            ? (string)($data['message'] ?? $data['status'] ?? $newState)
            : (string)$refund->fail_reason;

        $refund->update([
            'refund_id' => (string)($data['refund_id'] ?? $refund->refund_id),
            'amount_refund' => $amountRefund > 0 ? $amountRefund : (int)$refund->amount_refund,
            'refund_status' => $newState,
            'refunded_at' => $refundedAt ?: $refund->refunded_at,
            'fail_reason' => $failReason,
            'raw_payload' => $data,
        ]);

        $fresh = $this->findByRefundNo((string)$refund->out_refund_no) ?? $refund;
        if ($oldState !== $newState) {
            $this->events->dispatch(new WechatClientPaymentRefundStateChanged($fresh, $oldState, $newState, $data));
        }
        if ($oldState !== 'SUCCESS' && $newState === 'SUCCESS') {
            $this->events->dispatch(new WechatClientPaymentRefundSucceeded($fresh, $data));
        }

        return $fresh;
    }

    /**
     * 从退款参数中定位原支付单。
     *
     * 支持按本地支付订单 ID、支付号或业务订单号定位；业务订单号默认取最近一笔已支付单。
     *
     * @param array<string,mixed> $data
     */
    private function resolveOrder(array $data): ?WechatClientPaymentOrder
    {
        $orderId = (int)($data['order_id'] ?? 0);
        if ($orderId > 0) {
            $order = $this->orders->read($orderId);
            return $order instanceof WechatClientPaymentOrder ? $order : null;
        }

        $paymentNo = trim((string)($data['payment_no'] ?? $data['out_trade_no'] ?? ''));
        if ($paymentNo !== '') {
            return $this->orders->findByPaymentNo($paymentNo);
        }

        $orderNo = trim((string)($data['order_no'] ?? ''));
        if ($orderNo === '') {
            return null;
        }

        return WechatClientPaymentOrder::query()
            ->where('order_no', $orderNo)
            ->where('trade_state', 'SUCCESS')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * 获取业务订单号。
     */
    private function orderNo(WechatClientPaymentOrder $order): string
    {
        $orderNo = trim((string)$order->order_no);
        if ($orderNo === '') {
            throw new ErrorResponseException('支付订单业务订单号不能为空');
        }

        return $orderNo;
    }

    /**
     * 生成业务订单号下的下一笔退款号。
     *
     * 退款号规则：业务订单号 + 三位递增序号，例如 ORDER001001。
     */
    private function nextRefundNo(string $orderNo): string
    {
        if ($orderNo === '') {
            throw new ErrorResponseException('业务订单号不能为空');
        }
        if (strlen($orderNo) > 97) {
            throw new ErrorResponseException('业务订单号最多 97 位');
        }

        $max = 0;
        $items = WechatClientPaymentRefund::withTrashed()
            ->where('order_no', $orderNo)
            ->pluck('out_refund_no')
            ->all();
        foreach ($items as $item) {
            $refundNo = (string)$item;
            if (!str_starts_with($refundNo, $orderNo)) {
                continue;
            }
            $suffix = substr($refundNo, strlen($orderNo));
            if (preg_match('/^\d{3}$/', $suffix)) {
                $max = max($max, (int)$suffix);
            }
        }
        if ($max >= 999) {
            throw new ErrorResponseException('该业务订单退款发起次数已超过上限');
        }

        return $orderNo . str_pad((string)($max + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * 统计仍占用退款额度的金额，避免并发或重复退款超过原支付金额。
     */
    private function refundingAmount(int $orderId): int
    {
        return (int)WechatClientPaymentRefund::query()
            ->where('order_id', $orderId)
            ->whereIn('refund_status', ['PROCESSING', 'SUCCESS'])
            ->sum('amount_refund');
    }

    /**
     * 在数据库事务内锁定原支付单并创建本地退款记录。
     *
     * 退款额度和退款号都以同一原支付单为竞争边界；先 lockForUpdate 锁住支付单，再统计处理中/成功金额并取号，
     * 可避免两个并发退款同时通过额度校验后合计超过原订单金额。外部微信退款调用在事务提交后执行，避免长时间持锁。
     *
     * @return array{0:WechatClientPaymentRefund,1:string,2:array<string,mixed>}
     */
    private function createLocalRefund(
        WechatClientPaymentOrder $order,
        WechatClientPaymentMerchant $merchant,
        int $amountRefund,
        string $notifyUrl,
        string $reason,
    ): array {
        Db::beginTransaction();
        try {
            /** @var null|WechatClientPaymentOrder $locked */
            $locked = WechatClientPaymentOrder::query()
                ->where('id', (int)$order->id)
                ->lockForUpdate()
                ->first();
            if (!$locked instanceof WechatClientPaymentOrder) {
                throw new ErrorResponseException('支付订单不存在');
            }
            if ((string)$locked->trade_state !== 'SUCCESS') {
                throw new ErrorResponseException('仅支付成功的订单可以退款');
            }

            $pendingAmount = $this->refundingAmount((int)$locked->id);
            if ($pendingAmount + $amountRefund > (int)$locked->amount_total) {
                throw new ErrorResponseException('累计退款金额不能超过订单金额');
            }

            $orderNo = $this->orderNo($locked);
            $outRefundNo = $this->nextRefundNo($orderNo);
            $payload = [
                'out_trade_no' => (string)$locked->out_trade_no,
                'out_refund_no' => $outRefundNo,
                'notify_url' => $notifyUrl,
                'reason' => $reason,
                'amount' => [
                    'refund' => $amountRefund,
                    'total' => (int)$locked->amount_total,
                    'currency' => 'CNY',
                ],
            ];

            // 先写入 PROCESSING 状态占用退款额度，后续微信退款通知或主动查询会继续推进状态。
            /** @var WechatClientPaymentRefund $refund */
            $refund = $this->mapper->create([
                'tenant_id' => (int)$locked->tenant_id,
                'merchant_id' => (int)$merchant->id,
                'order_id' => (int)$locked->id,
                'order_no' => $orderNo,
                'out_trade_no' => (string)$locked->out_trade_no,
                'out_refund_no' => $outRefundNo,
                'amount_total' => (int)$locked->amount_total,
                'amount_refund' => $amountRefund,
                'notify_url' => $notifyUrl,
                'refund_status' => 'PROCESSING',
                'reason' => $reason,
                'raw_payload' => ['request' => $payload],
            ]);
            Db::commit();

            return [$refund, $outRefundNo, $payload];
        } catch (\Throwable $exception) {
            Db::rollBack();
            throw $exception;
        }
    }

    /**
     * 从查询参数中定位本地退款记录。
     *
     * 支持按本地退款 ID、商户退款号、微信退款单号或业务订单号查询。
     *
     * @param array<string,mixed> $data
     */
    private function resolveRefund(array $data): ?WechatClientPaymentRefund
    {
        $id = (int)($data['id'] ?? $data['refund_record_id'] ?? 0);
        if ($id > 0) {
            $refund = $this->read($id);
            return $refund instanceof WechatClientPaymentRefund ? $refund : null;
        }

        $refundNo = trim((string)($data['refund_no'] ?? $data['out_refund_no'] ?? ''));
        if ($refundNo !== '') {
            return $this->findByRefundNo($refundNo);
        }

        $wechatRefundId = trim((string)($data['refund_id'] ?? ''));
        if ($wechatRefundId !== '') {
            return WechatClientPaymentRefund::query()->where('refund_id', $wechatRefundId)->first();
        }

        $orderNo = trim((string)($data['order_no'] ?? ''));
        if ($orderNo === '') {
            return null;
        }

        return WechatClientPaymentRefund::query()
            ->where('order_no', $orderNo)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * 判断退款状态是否已经进入本地终态。
     */
    private function isRefundFinished(string $state): bool
    {
        return in_array($state, ['SUCCESS', 'CLOSED', 'ABNORMAL', 'FAIL'], true);
    }

    /**
     * 校验微信退款通知归属商户。
     *
     * 退款通知同样按 URL 中商户 ID 解密，业务写入前需再核对微信返回商户号，避免错配通知推进其它商户退款状态。
     *
     * @param array<string,mixed> $data
     */
    private function assertNotificationMerchant(WechatClientPaymentMerchant $merchant, array $data): void
    {
        $mchId = trim((string)($data['mchid'] ?? $data['mch_id'] ?? ''));
        if ($mchId !== '' && $mchId !== (string)$merchant->mch_id) {
            throw new ErrorResponseException('退款通知商户号与本地商户不一致');
        }
    }

    /**
     * 将微信 RFC3339 时间转换为系统使用的上海时区时间。
     */
    private function normalizeWechatTime(string $time): string
    {
        $time = trim($time);
        if ($time === '') {
            return date('Y-m-d H:i:s');
        }
        try {
            return (new \DateTimeImmutable($time))->setTimezone(new \DateTimeZone('Asia/Shanghai'))->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return $time;
        }
    }
}
