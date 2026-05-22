<?php

declare(strict_types=1);

namespace Plugin\WechatClient\Controller;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Library\CoreController;
use Library\Events\Annotation\Auth;
use Library\Events\Annotation\Logger;
use Plugin\WechatClient\Service\WechatClientPaymentRefundService;

/**
 * 微信支付退款后台管理接口。
 *
 * 负责退款记录查看、人工查询补偿和后台退款入口；常规业务退款建议直接调用退款服务。
 */
#[Auth(name: '微信支付退款')]
#[Controller(prefix: 'wechat-client/payment')]
final class PaymentRefundController extends CoreController
{
    public function __construct(
        protected WechatClientPaymentRefundService $service
    ) {}

    /**
     * 获取微信退款记录列表。
     */
    #[GetMapping(path: 'refund')]
    #[Auth(name: '微信退款列表', type: Auth::CHECK, menu: true, code: 'wechat.client.payment.refund.index')]
    public function index(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getPageList($request->all()));
    }

    /**
     * 查询单笔退款状态；本地未完成时会主动查微信并同步本地记录。
     */
    #[GetMapping(path: 'refund/query')]
    #[Auth(name: '查询微信退款订单', type: Auth::CHECK, menu: false, code: 'wechat.client.payment.refund.query')]
    #[Logger(name: '查询微信退款订单')]
    public function query(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->queryRefund($request->all()));
    }

    /**
     * 后台人工发起退款，常规业务退款建议调用 WechatClientPaymentService。
     */
    #[PostMapping(path: 'refund/create')]
    #[Auth(name: '发起微信退款', type: Auth::CHECK, menu: false, code: 'wechat.client.payment.refund.create')]
    #[Logger(name: '发起微信退款')]
    public function refund(RequestInterface $request): array
    {
        $this->success('退款已提交', $this->service->refund($request->all()));
    }
}
