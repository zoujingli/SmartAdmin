<?php

declare(strict_types=1);

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
use Plugin\WechatClient\Service\WechatClientMediaService;

/**
 * 微信素材后台管理接口。
 *
 * 负责本地素材维护、官方素材同步和永久素材上传。
 */
#[Auth(name: '微信素材')]
#[Controller(prefix: 'wechat-client/media')]
final class MediaController extends CoreController
{
    public function __construct(
        protected WechatClientMediaService $service
    ) {}

    #[GetMapping(path: 'index')]
    #[Auth(name: '微信素材列表', type: Auth::CHECK, menu: true, code: 'wechat.client.media.index')]
    public function index(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getPageList($request->all()));
    }

    #[PostMapping(path: 'create')]
    #[Auth(name: '保存微信素材', type: Auth::CHECK, menu: false, code: 'wechat.client.media.save')]
    #[Logger(name: '保存微信素材')]
    public function create(RequestInterface $request): array
    {
        $this->success('创建成功', $this->service->create($request->all()));
    }

    #[PutMapping(path: 'update/{id}')]
    #[Auth(name: '编辑微信素材', type: Auth::CHECK, menu: false, code: 'wechat.client.media.save')]
    #[Logger(name: '编辑微信素材')]
    public function update(int $id, RequestInterface $request): array
    {
        $this->service->update($id, $request->all());
        $this->success('更新成功', $this->service->read($id));
    }

    #[PostMapping(path: 'sync/{accountId}')]
    #[Auth(name: '同步微信素材', type: Auth::CHECK, menu: false, code: 'wechat.client.media.sync')]
    #[Logger(name: '同步微信素材')]
    public function sync(int $accountId, RequestInterface $request): array
    {
        $this->success('同步完成', $this->service->sync($accountId, (string)$request->input('media_type', 'image'), (int)$request->input('max_pages', 5)));
    }

    #[PostMapping(path: 'upload/{id}')]
    #[Auth(name: '上传微信素材', type: Auth::CHECK, menu: false, code: 'wechat.client.media.upload')]
    #[Logger(name: '上传微信素材')]
    public function upload(int $id): array
    {
        $this->success('上传成功', $this->service->uploadPermanent($id));
    }

    #[DeleteMapping(path: 'delete/{ids}')]
    #[Auth(name: '删除微信素材', type: Auth::CHECK, menu: false, code: 'wechat.client.media.delete')]
    #[Logger(name: '删除微信素材')]
    public function delete(string $ids): array
    {
        $this->deleteByIds($ids, fn (array $idArray) => $this->service->delete($idArray));
    }
}
