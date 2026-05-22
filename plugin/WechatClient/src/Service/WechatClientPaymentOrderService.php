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

use Hyperf\Database\Exception\QueryException;
use Library\Constants\System;
use Library\CoreService;
use Library\Exception\ErrorResponseException;
use Library\Helper\RequestHelper;
use Plugin\WechatClient\Event\WechatClientPaymentOrderPaid;
use Plugin\WechatClient\Event\WechatClientPaymentOrderStateChanged;
use Plugin\WechatClient\Mapper\WechatClientPaymentOrderMapper;
use Plugin\WechatClient\Model\WechatClientPaymentMerchant;
use Plugin\WechatClient\Model\WechatClientPaymentOrder;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * 微信支付订单服务。
 *
 * 负责本地支付单创建、微信下单、支付通知同步、主动查单补偿和支付事件派发。
 */
final class WechatClientPaymentOrderService extends CoreService
{
    public function __construct(
        protected WechatClientPaymentOrderMapper $mapper,
        private readonly WechatClientPaymentMerchantService $merchants,
        private readonly EventDispatcherInterface $events,
    ) {}

    /**
     * 发起微信支付。
     *
     * 本地会先生成支付号并落库，再调用微信下单接口。这样即使微信请求失败，
     * 也能保留失败上下文用于后台排查。
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function createPayment(array $data): array
    {
        $orderNo = trim((string)($data['order_no'] ?? $data['orderNo'] ?? ''));
        $amountTotal = (int)($data['amount_total'] ?? $data['amount'] ?? 0);
        $description = trim((string)($data['description'] ?? $data['body'] ?? ''));
        if ($orderNo === '') {
            throw new ErrorResponseException('业务订单号不能为空');
        }
        if (strlen($orderNo) > 97) {
            throw new ErrorResponseException('业务订单号最多 97 位');
        }
        if ($amountTotal <= 0) {
            throw new ErrorResponseException('支付金额必须大于 0');
        }
        if ($description === '') {
            throw new ErrorResponseException('支付描述不能为空');
        }

        $merchant = $this->merchants->defaultMerchant((int)($data['merchant_id'] ?? 0));
        $tradeType = $this->normalizeTradeType((string)($data['trade_type'] ?? 'JSAPI'));
        $openid = trim((string)($data['payer_openid'] ?? $data['openid'] ?? ''));
        if ($tradeType === 'JSAPI' && $openid === '') {
            throw new ErrorResponseException('JSAPI 支付必须传入付款人 OpenID');
        }

        $notifyUrl = $this->resolveNotifyUrl($merchant, 'order', (string)($data['notify_url'] ?? ''));
        [$order, $paymentNo, $payload] = $this->createLocalPaymentOrder(
            $merchant,
            $orderNo,
            $tradeType,
            $description,
            $amountTotal,
            $openid,
            $notifyUrl,
            $data,
        );

        try {
            $result = $this->merchants->paymentRequest($merchant, $this->transactionEndpoint($tradeType), $payload);
        } catch (\Throwable $exception) {
            // 微信下单异常时保留请求参数和错误信息，便于后台人工排查。
            $order->update([
                'trade_state' => 'PAYERROR',
                'raw_payload' => [
                    'request' => $payload,
                    'error' => $exception->getMessage(),
                ],
            ]);
            throw $exception;
        }

        $prepaymentId = (string)($result['prepay_id'] ?? '');
        $paymentParams = $tradeType === 'JSAPI' ? $this->merchants->makeJsapiPaymentParams($merchant, $prepaymentId) : [];
        $order->update([
            'prepayment_id' => $prepaymentId,
            'trade_state' => 'NOTPAY',
            'raw_payload' => [
                'request' => $payload,
                'response' => $result,
                'payment_params' => $paymentParams,
            ],
        ]);

        $fresh = $this->findByPaymentNo($paymentNo) ?? $order;

        return [
            'order' => $fresh,
            'order_no' => $orderNo,
            'payment_no' => $paymentNo,
            'out_trade_no' => $paymentNo,
            'trade_type' => $tradeType,
            'prepayment_id' => $prepaymentId,
            'code_url' => (string)($result['code_url'] ?? ''),
            'h5_url' => (string)($result['h5_url'] ?? ''),
            'payment_params' => $paymentParams,
            'raw' => $result,
        ];
    }

    /**
     * 处理微信支付 APIv3 通知；验签和资源解密由 SDK 完成，本地按商户订单号幂等更新。
     *
     * @param array<string,string> $headers
     * @param string $rawBody 微信原始 JSON body，必须用于 APIv3 签名验证
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    public function handleNotification(int $merchantId, array $headers, string $rawBody, array $body): array
    {
        $merchant = $this->merchants->requireMerchantForCallback($merchantId);
        // 支付平台回调没有登录态，先按商户归属租户恢复上下文，再执行后续验签和订单幂等写入。
        System::setTenantId((int)$merchant->tenant_id);
        $data = $this->merchants->paymentRequest($merchant, 'decrypt_notification', [], 'POST', [
            'headers' => $headers,
            'raw_body' => $rawBody,
            'body' => $body,
        ]);
        $amount = is_array($data['amount'] ?? null) ? $data['amount'] : [];
        $data['amount_total'] = (int)($amount['total'] ?? 0);
        $this->assertNotificationMerchant($merchant, $data);
        $order = $this->findByPaymentNo((string)($data['out_trade_no'] ?? ''));
        if (!$order instanceof WechatClientPaymentOrder) {
            throw new ErrorResponseException('支付订单不存在');
        }
        if ((int)$order->merchant_id !== (int)$merchant->id) {
            throw new ErrorResponseException('支付通知商户与本地订单不一致');
        }

        $this->applyPaymentData($order, $data);

        return $data;
    }

    /**
     * 按商户支付号查找本地支付单。
     */
    public function findByPaymentNo(string $paymentNo): ?WechatClientPaymentOrder
    {
        $paymentNo = trim($paymentNo);
        if ($paymentNo === '') {
            return null;
        }

        return WechatClientPaymentOrder::query()->where('out_trade_no', $paymentNo)->first();
    }

    /**
     * 查询支付状态：本地已终态直接返回，本地未完成时查询微信并同步后返回。
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function queryPayment(array $data): array
    {
        $order = $this->resolvePaymentOrder($data);
        if (!$order instanceof WechatClientPaymentOrder) {
            throw new ErrorResponseException('支付订单不存在');
        }

        $onlineQueried = false;
        $onlineResult = [];
        if (!$this->isPaymentFinished((string)$order->trade_state)) {
            $onlineQueried = true;
            $onlineResult = $this->syncPayment((string)$order->out_trade_no, true);
        }

        $fresh = $this->findByPaymentNo((string)$order->out_trade_no) ?? $order;

        return [
            'order' => $fresh,
            'order_no' => (string)$fresh->order_no,
            'payment_no' => (string)$fresh->out_trade_no,
            'trade_state' => (string)$fresh->trade_state,
            'local_finished' => $this->isPaymentFinished((string)$fresh->trade_state),
            'online_queried' => $onlineQueried,
            'online' => $onlineResult,
        ];
    }

    /**
     * 主动查单补偿。业务应优先依赖微信通知；此方法用于通知丢失或人工排查。
     *
     * $force=false 时，本地已经进入终态会直接返回，避免重复访问微信接口。
     *
     * @return array<string,mixed>
     */
    public function syncPayment(string $paymentNo, bool $force = false): array
    {
        $order = $this->findByPaymentNo($paymentNo);
        if (!$order instanceof WechatClientPaymentOrder) {
            throw new ErrorResponseException('支付订单不存在');
        }
        if (!$force && in_array((string)$order->trade_state, ['SUCCESS', 'CLOSED', 'REVOKED', 'PAYERROR'], true)) {
            return ['trade_state' => (string)$order->trade_state];
        }

        $merchant = $this->merchants->requireMerchant((int)$order->merchant_id);
        $result = $this->merchants->paymentRequest(
            $merchant,
            'v3/pay/transactions/out-trade-no/' . rawurlencode($paymentNo),
            ['mchid' => (string)$merchant->mch_id],
            'GET',
        );
        $result['out_trade_no'] = $paymentNo;
        $this->applyPaymentData($order, $result);

        return $result;
    }

    /**
     * 应用微信支付通知或查单结果到本地支付单。
     *
     * 该方法是支付状态推进的唯一入口：会做金额校验、更新时间和交易号，
     * 并在状态变化或支付成功时派发内部事件。
     *
     * @param array<string,mixed> $data
     */
    public function applyPaymentData(WechatClientPaymentOrder $order, array $data): WechatClientPaymentOrder
    {
        $oldState = (string)$order->trade_state;
        $amount = is_array($data['amount'] ?? null) ? $data['amount'] : [];
        $payer = is_array($data['payer'] ?? null) ? $data['payer'] : [];
        $newState = trim((string)($data['trade_state'] ?? ''));
        if ($newState === '' && trim((string)($data['transaction_id'] ?? '')) !== '') {
            $newState = 'SUCCESS';
        }
        if ($newState === '') {
            $newState = $oldState;
        }
        $amountTotal = (int)($data['amount_total'] ?? $amount['total'] ?? 0);
        if ($amountTotal > 0 && (int)$order->amount_total > 0 && $amountTotal !== (int)$order->amount_total) {
            throw new ErrorResponseException('支付通知金额与本地订单金额不一致');
        }

        $paidAt = $newState === 'SUCCESS' ? $this->normalizeWechatTime((string)($data['success_time'] ?? '')) : null;
        $order->update([
            'appid' => (string)($data['appid'] ?? $order->appid),
            'mch_id' => (string)($data['mchid'] ?? $data['mch_id'] ?? $order->mch_id),
            'transaction_id' => (string)($data['transaction_id'] ?? $order->transaction_id),
            'trade_type' => (string)($data['trade_type'] ?? $order->trade_type),
            'description' => (string)($data['description'] ?? $order->description),
            'amount_total' => $amountTotal > 0 ? $amountTotal : (int)$order->amount_total,
            'payer_openid' => (string)($payer['openid'] ?? $data['openid'] ?? $order->payer_openid),
            'trade_state' => $newState,
            'paid_at' => $paidAt ?: $order->paid_at,
            'raw_payload' => $data,
        ]);

        $fresh = $this->findByPaymentNo((string)$order->out_trade_no) ?? $order;
        if ($oldState !== $newState) {
            $this->events->dispatch(new WechatClientPaymentOrderStateChanged($fresh, $oldState, $newState, $data));
        }
        if ($oldState !== 'SUCCESS' && $newState === 'SUCCESS') {
            $this->events->dispatch(new WechatClientPaymentOrderPaid($fresh, $data));
        }

        return $fresh;
    }

    /**
     * 解析支付或退款通知地址。
     *
     * HTTP 请求内会根据当前域名生成；CLI、队列等无请求上下文场景必须显式传完整 URL。
     */
    public function resolveNotifyUrl(WechatClientPaymentMerchant $merchant, string $scene, string $notifyUrl = ''): string
    {
        $notifyUrl = trim($notifyUrl);
        if ($notifyUrl === '') {
            $path = $scene === 'refund'
                ? '/wechat-client/api/payment/notify/refund/' . (int)$merchant->id
                : '/wechat-client/api/payment/notify/order/' . (int)$merchant->id;
            $notifyUrl = RequestHelper::url($path);
        }
        if (!preg_match('#^https?://#i', $notifyUrl)) {
            throw new ErrorResponseException('微信支付通知地址必须是完整 URL');
        }

        return $notifyUrl;
    }

    /**
     * 生成业务订单号下的下一笔支付号。
     *
     * 支付号规则：业务订单号 + 三位递增序号，例如 ORDER001001。
     */
    private function nextPaymentNo(string $orderNo): string
    {
        $max = 0;
        $items = WechatClientPaymentOrder::withTrashed()
            ->where('order_no', $orderNo)
            ->pluck('out_trade_no')
            ->all();
        foreach ($items as $item) {
            $paymentNo = (string)$item;
            if (!str_starts_with($paymentNo, $orderNo)) {
                continue;
            }
            $suffix = substr($paymentNo, strlen($orderNo));
            if (preg_match('/^\d{3}$/', $suffix)) {
                $max = max($max, (int)$suffix);
            }
        }
        if ($max >= 999) {
            throw new ErrorResponseException('该业务订单支付发起次数已超过上限');
        }

        return $orderNo . str_pad((string)($max + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * 生成并写入本地支付单。
     *
     * 并发发起同一业务订单支付时，两个请求可能同时算出相同序号；这里在唯一索引冲突时重新取号，
     * 确保微信下单前本地 payment_no 已经稳定落库，且不会把数据库异常暴露给业务调用方。
     *
     * @param array<string,mixed> $source
     * @return array{0:WechatClientPaymentOrder,1:string,2:array<string,mixed>}
     */
    private function createLocalPaymentOrder(
        WechatClientPaymentMerchant $merchant,
        string $orderNo,
        string $tradeType,
        string $description,
        int $amountTotal,
        string $openid,
        string $notifyUrl,
        array $source,
    ): array {
        $attempts = 0;
        while (true) {
            $paymentNo = $this->nextPaymentNo($orderNo);
            $payload = [
                'appid' => (string)$merchant->appid,
                'mchid' => (string)$merchant->mch_id,
                'description' => mb_substr($description, 0, 127),
                'out_trade_no' => $paymentNo,
                'notify_url' => $notifyUrl,
                'amount' => [
                    'total' => $amountTotal,
                    'currency' => 'CNY',
                ],
            ];
            if ($tradeType === 'JSAPI') {
                $payload['payer'] = ['openid' => $openid];
            }
            if (isset($source['attach'])) {
                $payload['attach'] = (string)$source['attach'];
            }
            if (isset($source['time_expire'])) {
                $payload['time_expire'] = (string)$source['time_expire'];
            }

            try {
                // 先写入 CREATED 状态，确保微信下单前后都能通过 payment_no 定位本地记录。
                /** @var WechatClientPaymentOrder $order */
                $order = $this->mapper->create([
                    'tenant_id' => (int)$merchant->tenant_id,
                    'merchant_id' => (int)$merchant->id,
                    'appid' => (string)$merchant->appid,
                    'mch_id' => (string)$merchant->mch_id,
                    'order_no' => $orderNo,
                    'out_trade_no' => $paymentNo,
                    'trade_type' => $tradeType,
                    'description' => $description,
                    'amount_total' => $amountTotal,
                    'payer_openid' => $openid,
                    'notify_url' => $notifyUrl,
                    'trade_state' => 'CREATED',
                    'raw_payload' => ['request' => $payload],
                ]);

                return [$order, $paymentNo, $payload];
            } catch (QueryException $exception) {
                if (!$this->isUniqueConstraintViolation($exception)) {
                    throw $exception;
                }
                if (++$attempts >= 3) {
                    throw new ErrorResponseException('支付号生成冲突，请稍后重试');
                }
            }
        }
    }

    /**
     * 判断数据库异常是否为唯一索引冲突，同时覆盖 MySQL 与 SQLite 测试环境错误文案。
     */
    private function isUniqueConstraintViolation(\Throwable $exception): bool
    {
        $message = $exception->getMessage();

        return (string)$exception->getCode() === '23000'
            || str_contains($message, 'Duplicate entry')
            || str_contains($message, 'UNIQUE constraint failed');
    }

    /**
     * 标准化并限制当前支持的微信支付类型。
     */
    private function normalizeTradeType(string $tradeType): string
    {
        $tradeType = strtoupper(trim($tradeType) ?: 'JSAPI');
        if (!in_array($tradeType, ['JSAPI', 'NATIVE'], true)) {
            throw new ErrorResponseException('微信支付暂仅支持 JSAPI 或 NATIVE');
        }

        return $tradeType;
    }

    /**
     * 根据支付类型拼接微信 APIv3 下单接口路径。
     */
    private function transactionEndpoint(string $tradeType): string
    {
        return 'v3/pay/transactions/' . strtolower($tradeType);
    }

    /**
     * 从查询参数中定位本地支付单。
     *
     * 支持按本地 ID、支付号、业务订单号查询；业务订单号会取最新一笔支付单。
     *
     * @param array<string,mixed> $data
     */
    private function resolvePaymentOrder(array $data): ?WechatClientPaymentOrder
    {
        $id = (int)($data['id'] ?? $data['order_id'] ?? 0);
        if ($id > 0) {
            $order = $this->read($id);
            return $order instanceof WechatClientPaymentOrder ? $order : null;
        }

        $paymentNo = trim((string)($data['payment_no'] ?? $data['out_trade_no'] ?? ''));
        if ($paymentNo !== '') {
            return $this->findByPaymentNo($paymentNo);
        }

        $orderNo = trim((string)($data['order_no'] ?? ''));
        if ($orderNo === '') {
            return null;
        }

        return WechatClientPaymentOrder::query()
            ->where('order_no', $orderNo)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * 判断支付状态是否已经进入本地终态。
     */
    private function isPaymentFinished(string $state): bool
    {
        return in_array($state, ['SUCCESS', 'CLOSED', 'REVOKED', 'PAYERROR'], true);
    }

    /**
     * 校验微信支付通知归属商户。
     *
     * 微信通知 URL 带本地商户 ID，资源解密后仍必须核对 AppID/商户号，避免同平台证书或配置误用时把其它商户通知写入本地订单。
     *
     * @param array<string,mixed> $data
     */
    private function assertNotificationMerchant(WechatClientPaymentMerchant $merchant, array $data): void
    {
        $appid = trim((string)($data['appid'] ?? ''));
        if ($appid !== '' && $appid !== (string)$merchant->appid) {
            throw new ErrorResponseException('支付通知 AppID 与本地商户不一致');
        }

        $mchId = trim((string)($data['mchid'] ?? $data['mch_id'] ?? ''));
        if ($mchId !== '' && $mchId !== (string)$merchant->mch_id) {
            throw new ErrorResponseException('支付通知商户号与本地商户不一致');
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
