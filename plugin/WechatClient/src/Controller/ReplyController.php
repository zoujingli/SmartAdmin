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
use Plugin\WechatClient\Service\WechatClientReplyRuleService;

/**
 * 微信自动回复后台管理接口。
 *
 * 负责订阅、默认、关键词和菜单点击回复规则维护。
 */
#[Auth(name: '微信自动回复')]
#[Controller(prefix: 'wechat-client/reply')]
final class ReplyController extends CoreController
{
    public function __construct(
        protected WechatClientReplyRuleService $service
    ) {}

    #[GetMapping(path: 'index')]
    #[Auth(name: '微信自动回复列表', type: Auth::CHECK, menu: true, code: 'wechat.client.reply.index')]
    public function index(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getPageList($request->all()));
    }

    #[PostMapping(path: 'create')]
    #[Auth(name: '保存微信自动回复', type: Auth::CHECK, menu: false, code: 'wechat.client.reply.save')]
    #[Logger(name: '保存微信自动回复')]
    public function create(RequestInterface $request): array
    {
        $this->success('创建成功', $this->service->create($request->all()));
    }

    #[PutMapping(path: 'update/{id}')]
    #[Auth(name: '编辑微信自动回复', type: Auth::CHECK, menu: false, code: 'wechat.client.reply.save')]
    #[Logger(name: '编辑微信自动回复')]
    public function update(int $id, RequestInterface $request): array
    {
        $this->service->update($id, $request->all());
        $this->success('更新成功', $this->service->read($id));
    }

    #[DeleteMapping(path: 'delete/{ids}')]
    #[Auth(name: '删除微信自动回复', type: Auth::CHECK, menu: false, code: 'wechat.client.reply.delete')]
    #[Logger(name: '删除微信自动回复')]
    public function delete(string $ids): array
    {
        $this->deleteByIds($ids, fn (array $idArray) => $this->service->delete($idArray));
    }
}
