<?php

declare(strict_types=1);

namespace Plugin\WechatClient\Controller;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Annotation\PutMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Library\CoreController;
use Library\Events\Annotation\Auth;
use Library\Events\Annotation\Logger;
use Plugin\WechatClient\Service\WechatClientPaymentMerchantService;

/**
 * 微信支付商户后台管理接口。
 *
 * 仅负责商户配置列表、创建和编辑入口；证书、密钥等敏感字段由 Service 统一过滤、加密和脱敏。
 */
#[Auth(name: '微信支付商户')]
#[Controller(prefix: 'wechat-client/payment')]
final class PaymentMerchantController extends CoreController
{
    public function __construct(
        protected WechatClientPaymentMerchantService $service
    ) {}

    /**
     * 获取微信支付商户配置列表。
     */
    #[GetMapping(path: 'merchant')]
    #[Auth(name: '微信支付商户列表', type: Auth::CHECK, menu: true, code: 'wechat.client.payment.merchant.index')]
    public function index(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getPageList($request->all()));
    }

    /**
     * 创建微信支付商户配置。
     */
    #[PostMapping(path: 'merchant/create')]
    #[Auth(name: '保存微信支付商户', type: Auth::CHECK, menu: false, code: 'wechat.client.payment.merchant.save')]
    #[Logger(name: '保存微信支付商户', excludeFields: ['api_v3_key', 'merchant_serial', 'merchant_private_key', 'platform_public_key', 'platform_serial'])]
    public function create(RequestInterface $request): array
    {
        $this->success('创建成功', $this->service->create($request->all()));
    }

    /**
     * 更新微信支付商户配置，敏感字段支持前端掩码回传时保持原值。
     */
    #[PutMapping(path: 'merchant/update/{id}')]
    #[Auth(name: '编辑微信支付商户', type: Auth::CHECK, menu: false, code: 'wechat.client.payment.merchant.save')]
    #[Logger(name: '编辑微信支付商户', excludeFields: ['api_v3_key', 'merchant_serial', 'merchant_private_key', 'platform_public_key', 'platform_serial'])]
    public function update(int $id, RequestInterface $request): array
    {
        $this->service->update($id, $request->all());
        $this->success('更新成功', $this->service->read($id));
    }
}
