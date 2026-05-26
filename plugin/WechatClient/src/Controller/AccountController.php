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
use Hyperf\HttpServer\Annotation\DeleteMapping;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Annotation\PutMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Library\CoreController;
use Library\Events\Annotation\Auth;
use Library\Events\Annotation\Logger;
use Plugin\WechatClient\Service\WechatClientAccountService;

/**
 * 微信接口账号后台管理接口。
 *
 * 负责公众号/小程序接口账号的增删改查，敏感字段由服务层统一加密和脱敏。
 */
#[Auth(name: '微信接口账号')]
#[Controller(prefix: 'wechat-client/account')]
final class AccountController extends CoreController
{
    public function __construct(
        protected WechatClientAccountService $service
    ) {}

    /**
     * 获取微信接口账号列表。
     */
    #[GetMapping(path: 'index')]
    #[Auth(name: '微信接口账号列表', type: Auth::CHECK, menu: true, code: 'wechat.client.account.index')]
    public function index(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getPageList($request->all()));
    }

    /**
     * 获取微信接口账号详情。
     */
    #[GetMapping(path: 'info/{id}')]
    #[Auth(name: '微信接口账号详情', type: Auth::CHECK, menu: false, code: 'wechat.client.account.index')]
    public function info(int $id): array
    {
        $this->respondFound($this->service->read($id));
    }

    /**
     * 创建微信接口账号。
     */
    #[PostMapping(path: 'create')]
    #[Auth(name: '新增微信接口账号', type: Auth::CHECK, menu: false, code: 'wechat.client.account.create')]
    #[Logger(name: '新增微信接口账号', excludeFields: ['appsecret', 'token', 'encodingaeskey', 'access_token', 'refresh_token', 'extra.gateway_client_key', 'extra.gateway_client_secret'])]
    public function create(RequestInterface $request): array
    {
        $this->success('创建成功', $this->service->create($request->all()));
    }

    /**
     * 更新微信接口账号，敏感字段支持前端掩码回传时保持原值。
     */
    #[PutMapping(path: 'update/{id}')]
    #[Auth(name: '编辑微信接口账号', type: Auth::CHECK, menu: false, code: 'wechat.client.account.update')]
    #[Logger(name: '编辑微信接口账号', excludeFields: ['appsecret', 'token', 'encodingaeskey', 'access_token', 'refresh_token', 'extra.gateway_client_key', 'extra.gateway_client_secret'])]
    public function update(int $id, RequestInterface $request): array
    {
        $this->service->update($id, $request->all());
        $this->success('更新成功', $this->service->read($id));
    }

    /**
     * 批量删除微信接口账号。
     */
    #[DeleteMapping(path: 'delete/{ids}')]
    #[Auth(name: '删除微信接口账号', type: Auth::CHECK, menu: false, code: 'wechat.client.account.delete')]
    #[Logger(name: '删除微信接口账号')]
    public function delete(string $ids): array
    {
        $this->deleteByIds($ids, fn (array $idArray) => $this->service->delete($idArray));
    }
}
