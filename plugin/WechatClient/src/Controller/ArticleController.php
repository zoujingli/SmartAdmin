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
use Plugin\WechatClient\Service\WechatClientArticleService;

/**
 * 微信文章后台管理接口。
 *
 * 负责本地图文维护、上传草稿、发布和发布状态查询。
 */
#[Auth(name: '微信文章')]
#[Controller(prefix: 'wechat-client/article')]
final class ArticleController extends CoreController
{
    public function __construct(
        protected WechatClientArticleService $service
    ) {}

    #[GetMapping(path: 'index')]
    #[Auth(name: '微信文章列表', type: Auth::CHECK, menu: true, code: 'wechat.client.article.index')]
    public function index(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getPageList($request->all()));
    }

    #[PostMapping(path: 'create')]
    #[Auth(name: '保存微信文章', type: Auth::CHECK, menu: false, code: 'wechat.client.article.save')]
    #[Logger(name: '保存微信文章')]
    public function create(RequestInterface $request): array
    {
        $this->success('创建成功', $this->service->create($request->all()));
    }

    #[PutMapping(path: 'update/{id}')]
    #[Auth(name: '编辑微信文章', type: Auth::CHECK, menu: false, code: 'wechat.client.article.save')]
    #[Logger(name: '编辑微信文章')]
    public function update(int $id, RequestInterface $request): array
    {
        $this->service->update($id, $request->all());
        $this->success('更新成功', $this->service->read($id));
    }

    #[PostMapping(path: 'upload-draft/{id}')]
    #[Auth(name: '上传微信草稿', type: Auth::CHECK, menu: false, code: 'wechat.client.article.upload-draft')]
    #[Logger(name: '上传微信草稿')]
    public function uploadDraft(int $id): array
    {
        $this->success('上传成功', $this->service->uploadDraft($id));
    }

    #[PostMapping(path: 'publish/{id}')]
    #[Auth(name: '发布微信文章', type: Auth::CHECK, menu: false, code: 'wechat.client.article.publish')]
    #[Logger(name: '发布微信文章')]
    public function publish(int $id): array
    {
        $this->success('发布已提交', $this->service->publish($id));
    }

    #[GetMapping(path: 'query/{id}')]
    #[Auth(name: '查询微信文章发布状态', type: Auth::CHECK, menu: false, code: 'wechat.client.article.query')]
    #[Logger(name: '查询微信文章发布状态')]
    public function query(int $id): array
    {
        $this->success('查询成功', $this->service->queryPublishStatus($id));
    }

    #[DeleteMapping(path: 'delete/{ids}')]
    #[Auth(name: '删除微信文章', type: Auth::CHECK, menu: false, code: 'wechat.client.article.delete')]
    #[Logger(name: '删除微信文章')]
    public function delete(string $ids): array
    {
        $this->deleteByIds($ids, fn (array $idArray) => $this->service->delete($idArray));
    }
}
