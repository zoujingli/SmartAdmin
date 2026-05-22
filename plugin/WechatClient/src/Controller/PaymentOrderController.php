<?php

declare(strict_types=1);

namespace Plugin\WechatClient\Controller;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Library\CoreController;
use Library\Events\Annotation\Auth;
use Library\Events\Annotation\Logger;
use Plugin\WechatClient\Service\WechatClientPaymentOrderService;

/**
 * 微信支付订单后台管理接口。
 *
 * 后台只提供订单查看和人工查询补偿；业务发起支付仍应通过支付服务编排完成。
 */
#[Auth(name: '微信支付订单')]
#[Controller(prefix: 'wechat-client/payment')]
final class PaymentOrderController extends CoreController
{
    public function __construct(
        protected WechatClientPaymentOrderService $service
    ) {}

    /**
     * 获取微信支付订单列表。
     */
    #[GetMapping(path: 'order')]
    #[Auth(name: '微信支付订单列表', type: Auth::CHECK, menu: true, code: 'wechat.client.payment.order.index')]
    public function index(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getPageList($request->all()));
    }

    /**
     * 查询单笔支付状态；本地未完成时会主动查微信并同步本地记录。
     */
    #[GetMapping(path: 'order/query')]
    #[Auth(name: '查询微信支付订单', type: Auth::CHECK, menu: false, code: 'wechat.client.payment.order.query')]
    #[Logger(name: '查询微信支付订单')]
    public function query(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->queryPayment($request->all()));
    }
}
