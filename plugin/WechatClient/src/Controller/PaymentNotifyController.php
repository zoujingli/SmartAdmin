<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Plugin\WechatClient\Controller;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Library\CoreController;
use Plugin\WechatClient\Service\WechatClientPaymentOrderService;
use Plugin\WechatClient\Service\WechatClientPaymentRefundService;
use Psr\Http\Message\ResponseInterface;

/**
 * 微信支付通知入口。
 *
 * 这里仅负责接收微信 APIv3 回调、整理请求头和请求体，并把验签、解密、
 * 状态同步和事件派发交给支付/退款服务处理。
 */
#[Controller(prefix: 'wechat-client/api/payment')]
final class PaymentNotifyController extends CoreController
{
    public function __construct(
        protected WechatClientPaymentOrderService $service,
        private readonly WechatClientPaymentRefundService $refunds,
    ) {}

    /**
     * 接收微信支付成功或状态变化通知。
     */
    #[PostMapping(path: 'notify/order/{merchantId}')]
    public function orderNotify(int $merchantId, RequestInterface $request): ResponseInterface
    {
        try {
            [$headers, $rawBody, $body] = $this->notificationPayload($request);
            $this->service->handleNotification($merchantId, $headers, $rawBody, $body);
            return $this->wechatSuccess();
        } catch (\Throwable $exception) {
            return $this->wechatFail($exception->getMessage());
        }
    }

    /**
     * 接收微信退款状态通知。
     */
    #[PostMapping(path: 'notify/refund/{merchantId}')]
    public function refundNotify(int $merchantId, RequestInterface $request): ResponseInterface
    {
        try {
            [$headers, $rawBody, $body] = $this->notificationPayload($request);
            $this->refunds->handleNotification($merchantId, $headers, $rawBody, $body);
            return $this->wechatSuccess();
        } catch (\Throwable $exception) {
            return $this->wechatFail($exception->getMessage());
        }
    }

    /**
     * 将微信验签需要的 HTTP 头和 JSON 请求体整理为服务层统一入参。
     *
     * @return array{0:array<string,string>,1:string,2:array<string,mixed>}
     */
    private function notificationPayload(RequestInterface $request): array
    {
        $headers = [];
        foreach ($request->getHeaders() as $name => $values) {
            $headers[$name] = implode(',', $values);
        }
        // APIv3 验签必须使用原始 body；解析后的数组只用于业务读取 resource 和后续状态字段。
        $rawBody = (string)$request->getBody();
        $body = json_decode($rawBody, true);

        return [$headers, $rawBody, is_array($body) ? $body : []];
    }

    /**
     * 微信通知成功响应，必须返回 SUCCESS 才会停止微信重试。
     */
    private function wechatSuccess(): ResponseInterface
    {
        return $this->wechatJson(['code' => 'SUCCESS', 'message' => '成功']);
    }

    /**
     * 微信通知失败响应；返回 FAIL 后微信会按官方策略重试通知。
     */
    private function wechatFail(string $message): ResponseInterface
    {
        return $this->wechatJson(['code' => 'FAIL', 'message' => $message !== '' ? $message : '失败'], 500);
    }

    /**
     * 输出微信支付通知要求的 JSON 响应格式。
     *
     * @param array<string,string> $data
     */
    private function wechatJson(array $data, int $status = 200): ResponseInterface
    {
        return $this->response
            ->raw(json_encode($data, JSON_UNESCAPED_UNICODE) ?: '{"code":"FAIL","message":"失败"}')
            ->withHeader('content-type', 'application/json; charset=utf-8')
            ->withStatus($status);
    }
}
