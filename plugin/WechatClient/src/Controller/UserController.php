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
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Library\CoreController;
use Library\Events\Annotation\Auth;
use Library\Events\Annotation\Logger;
use Plugin\WechatClient\Service\WechatClientUserService;

/**
 * 微信粉丝后台管理接口。
 *
 * 提供粉丝列表查询和从微信官方接口同步粉丝资料的能力。
 */
#[Auth(name: '微信粉丝')]
#[Controller(prefix: 'wechat-client/user')]
final class UserController extends CoreController
{
    public function __construct(
        protected WechatClientUserService $service
    ) {}

    /**
     * 获取微信粉丝列表。
     */
    #[GetMapping(path: 'index')]
    #[Auth(name: '微信粉丝列表', type: Auth::CHECK, menu: true, code: 'wechat.client.user.index')]
    public function index(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getPageList($request->all()));
    }

    /**
     * 从微信官方接口同步指定账号的粉丝资料。
     */
    #[PostMapping(path: 'sync/{accountId}')]
    #[Auth(name: '同步微信粉丝', type: Auth::CHECK, menu: false, code: 'wechat.client.user.sync')]
    #[Logger(name: '同步微信粉丝')]
    public function sync(int $accountId, RequestInterface $request): array
    {
        $this->success('同步完成', $this->service->sync($accountId, (int)$request->input('max_pages', 20)));
    }
}
