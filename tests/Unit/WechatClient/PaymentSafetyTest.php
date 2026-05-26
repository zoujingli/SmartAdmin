<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Tests\Unit\WechatClient;

use Library\Constants\Status;
use Library\Constants\System;
use Library\Exception\ErrorResponseException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Plugin\WechatClient\Model\WechatClientAccount;
use Plugin\WechatClient\Model\WechatClientPaymentMerchant;
use Plugin\WechatClient\Model\WechatClientPaymentRefund;
use Plugin\WechatClient\Service\WechatClientAccountService;
use Plugin\WechatClient\Service\WechatClientPaymentMerchantService;
use Plugin\WechatClient\Service\WechatClientPaymentOrderService;
use Plugin\WechatClient\Service\WechatClientPaymentRefundService;

/**
 * @internal
 */
#[CoversClass(WechatClientAccountService::class)]
#[CoversClass(WechatClientPaymentMerchantService::class)]
#[CoversClass(WechatClientPaymentOrderService::class)]
#[CoversClass(WechatClientPaymentRefundService::class)]
final class PaymentSafetyTest extends TestCase
{
    public function testRequireAccountRejectsDisabledModelInstance(): void
    {
        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('微信接口账号不可用');

        $service = $this->service(WechatClientAccountService::class);
        $account = new WechatClientAccount(['status' => Status::DISABLED]);

        $service->requireAccount($account);
    }

    public function testGatewayUrlRejectsInlineQuery(): void
    {
        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('开放平台网关地址不能包含认证信息、查询参数或片段');

        $this->invokePrivate(WechatClientAccountService::class, 'assertGatewayUrl', 'https://example.com/wechat-service/api/rpc/jsonrpc?token=bad');
    }

    public function testDecodeExtraRejectsInvalidJson(): void
    {
        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('账号扩展配置格式错误');

        $this->invokePrivate(WechatClientAccountService::class, 'decodeExtra', '{"gateway_url":');
    }

    public function testRequireMerchantRejectsDisabledModelInstance(): void
    {
        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('微信支付商户不可用');

        $service = $this->service(WechatClientPaymentMerchantService::class);
        $merchant = new WechatClientPaymentMerchant(['status' => Status::DISABLED]);

        $service->requireMerchant($merchant);
    }

    public function testDefaultMerchantRejectsMissingTenantContext(): void
    {
        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('微信支付商户不可用');

        System::setTenantId(0);
        $service = $this->service(WechatClientPaymentMerchantService::class);

        // 未指定 merchant_id 且无租户上下文时不能默认取全平台第一条商户，避免真实支付串租户。
        $service->defaultMerchant();
    }

    public function testPaymentNotificationRejectsMismatchedAppid(): void
    {
        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('支付通知 AppID 与本地商户不一致');

        $this->invokePrivate(
            WechatClientPaymentOrderService::class,
            'assertNotificationMerchant',
            $this->merchant(),
            ['appid' => 'wx_other', 'mchid' => '1900000001'],
        );
    }

    public function testPaymentNotificationRejectsMismatchedMerchantId(): void
    {
        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('支付通知商户号与本地商户不一致');

        $this->invokePrivate(
            WechatClientPaymentOrderService::class,
            'assertNotificationMerchant',
            $this->merchant(),
            ['appid' => 'wx_appid', 'mchid' => '1900000002'],
        );
    }

    public function testRefundNotificationRejectsMismatchedMerchantId(): void
    {
        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('退款通知商户号与本地商户不一致');

        $this->invokePrivate(
            WechatClientPaymentRefundService::class,
            'assertNotificationMerchant',
            $this->merchant(),
            ['mchid' => '1900000002'],
        );
    }

    public function testRefundDataRejectsMismatchedPaymentNo(): void
    {
        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('退款通知支付号与本地记录不一致');

        $service = $this->service(WechatClientPaymentRefundService::class);
        $refund = new WechatClientPaymentRefund([
            'out_trade_no' => 'PAY001',
            'amount_total' => 100,
            'amount_refund' => 50,
            'refund_status' => 'PROCESSING',
        ]);

        $service->applyRefundData($refund, ['out_trade_no' => 'PAY002']);
    }

    public function testRefundDataRejectsMismatchedOrderAmount(): void
    {
        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('退款通知订单金额与本地订单金额不一致');

        $service = $this->service(WechatClientPaymentRefundService::class);
        $refund = new WechatClientPaymentRefund([
            'out_trade_no' => 'PAY001',
            'amount_total' => 100,
            'amount_refund' => 50,
            'refund_status' => 'PROCESSING',
        ]);

        $service->applyRefundData($refund, ['amount' => ['total' => 200, 'refund' => 50]]);
    }

    public function testNotificationMerchantValidationAllowsMatchingMerchant(): void
    {
        $this->expectNotToPerformAssertions();

        $merchant = $this->merchant();
        $this->invokePrivate(WechatClientPaymentOrderService::class, 'assertNotificationMerchant', $merchant, [
            'appid' => 'wx_appid',
            'mchid' => '1900000001',
        ]);
        $this->invokePrivate(WechatClientPaymentRefundService::class, 'assertNotificationMerchant', $merchant, [
            'mchid' => '1900000001',
        ]);
    }

    private function merchant(): WechatClientPaymentMerchant
    {
        return new WechatClientPaymentMerchant([
            'id' => 10,
            'appid' => 'wx_appid',
            'mch_id' => '1900000001',
            'status' => Status::ENABLED,
        ]);
    }

    /**
     * 单测只覆盖无数据库依赖的安全边界逻辑，使用反射避免构造真实 Mapper、SDK 和事件分发器。
     *
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    private function service(string $class): object
    {
        return (new \ReflectionClass($class))->newInstanceWithoutConstructor();
    }

    /**
     * @param class-string $class
     */
    private function invokePrivate(string $class, string $method, mixed ...$args): mixed
    {
        $reflection = new \ReflectionClass($class);
        $service = $reflection->newInstanceWithoutConstructor();
        $methodReflection = $reflection->getMethod($method);
        $methodReflection->setAccessible(true);

        return $methodReflection->invoke($service, ...$args);
    }
}
