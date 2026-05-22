<?php

declare(strict_types=1);

namespace Plugin\WechatService\Controller;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Library\CoreController;
use Plugin\WechatService\Service\WechatServiceRpcService;
use Psr\Http\Message\ResponseInterface;

#[Controller(prefix: 'wechat-service/api/rpc')]
final class RpcController extends CoreController
{
    public function __construct(
        protected WechatServiceRpcService $service
    ) {}

    #[PostMapping(path: 'jsonrpc')]
    public function jsonrpc(RequestInterface $request): ResponseInterface
    {
        $body = json_decode((string)$request->getBody(), true);
        $result = $this->service->handle((string)$request->input('token', ''), is_array($body) ? $body : []);

        return $this->response
            ->raw(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}')
            ->withHeader('content-type', 'application/json; charset=utf-8');
    }
}
