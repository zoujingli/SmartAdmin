<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Plugin\WechatService\Controller;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\DeleteMapping;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Annotation\PutMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Library\CoreController;
use Library\Events\Annotation\Auth;
use Library\Events\Annotation\Logger;
use Plugin\WechatService\Service\WechatServiceAuthService;

#[Auth(name: '微信授权账号')]
#[Controller(prefix: 'wechat-service/auth')]
final class AuthController extends CoreController
{
    public function __construct(
        protected WechatServiceAuthService $service
    ) {}

    #[GetMapping(path: 'index')]
    #[Auth(name: '授权账号列表', type: Auth::CHECK, menu: true, code: 'wechat.service.auth.index')]
    public function index(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getPageList($request->all()));
    }

    #[GetMapping(path: 'info/{id}')]
    #[Auth(name: '授权账号详情', type: Auth::CHECK, menu: false, code: 'wechat.service.auth.index')]
    public function info(int $id): array
    {
        $this->respondFound($this->service->read($id));
    }

    #[PutMapping(path: 'status/{id}')]
    #[Auth(name: '更新授权账号状态', type: Auth::CHECK, menu: false, code: 'wechat.service.auth.update')]
    #[Logger(name: '更新授权账号状态')]
    public function status(int $id, RequestInterface $request): array
    {
        $this->success('更新成功', $this->service->changeStatus($id, (int)$request->input('status', 1)));
    }

    #[PostMapping(path: 'sync/{id}')]
    #[Auth(name: '同步授权账号', type: Auth::CHECK, menu: false, code: 'wechat.service.auth.sync')]
    #[Logger(name: '同步授权账号')]
    public function sync(int $id): array
    {
        $this->success('同步成功', $this->service->sync($id));
    }

    #[DeleteMapping(path: 'delete/{ids}')]
    #[Auth(name: '删除授权账号', type: Auth::CHECK, menu: false, code: 'wechat.service.auth.delete')]
    #[Logger(name: '删除授权账号')]
    public function delete(string $ids): array
    {
        $this->deleteByIds($ids, fn (array $idArray) => $this->service->delete($idArray));
    }
}
