<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Plugin\Website\Controller;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\DeleteMapping;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Annotation\PutMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Library\CoreController;
use Library\Events\Annotation\Auth;
use Library\Events\Annotation\Logger;
use Plugin\Website\Service\WebsiteSiteService;

#[Auth(name: '官网站点管理')]
#[Controller(prefix: 'system/website/site')]
final class SystemSiteController extends CoreController
{
    public function __construct(
        protected WebsiteSiteService $service
    ) {}

    #[GetMapping(path: 'index')]
    #[Auth(name: '官网站点列表', type: Auth::CHECK, menu: true, code: 'website.site.index')]
    public function index(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getPageList($request->all()));
    }

    #[GetMapping(path: 'recycle')]
    #[Auth(name: '官网站点回收站', type: Auth::CHECK, menu: false, code: 'website.site.index')]
    public function recycle(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getRecycleList($request->all()));
    }

    #[GetMapping(path: 'info/{id}')]
    #[Auth(name: '官网站点详情', type: Auth::CHECK, menu: false, code: 'website.site.index')]
    public function info(int $id): array
    {
        $this->respondFound($this->service->read($id));
    }

    #[GetMapping(path: 'options')]
    #[Auth(name: '官网站点选项', type: Auth::CHECK, menu: false, code: 'website.site.index')]
    public function options(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->options($request->all()));
    }

    #[PostMapping(path: 'create')]
    #[Auth(name: '新增官网站点', type: Auth::CHECK, menu: false, code: 'website.site.create')]
    #[Logger(name: '新增官网站点')]
    public function create(RequestInterface $request): array
    {
        $this->success('创建成功', $this->service->create($request->all()));
    }

    #[PutMapping(path: 'update/{id}')]
    #[Auth(name: '编辑官网站点', type: Auth::CHECK, menu: false, code: 'website.site.update')]
    #[Logger(name: '编辑官网站点')]
    public function update(int $id, RequestInterface $request): array
    {
        $this->service->update($id, $request->all());
        $this->success('更新成功', $this->service->read($id));
    }

    #[PutMapping(path: 'status/{id}')]
    #[Auth(name: '更新官网站点状态', type: Auth::CHECK, menu: false, code: 'website.site.status')]
    #[Logger(name: '更新官网站点状态')]
    public function status(int $id, RequestInterface $request): array
    {
        $this->success('更新成功', $this->service->changeStatus($id, (int)$request->input('status', 1)));
    }

    #[DeleteMapping(path: 'delete/{ids}')]
    #[Auth(name: '删除官网站点', type: Auth::CHECK, menu: false, code: 'website.site.delete')]
    #[Logger(name: '删除官网站点')]
    public function delete(string $ids): array
    {
        $this->deleteByIds($ids, fn (array $idArray): bool => $this->service->delete($idArray));
    }

    #[PutMapping(path: 'recovery/{ids}')]
    #[Auth(name: '恢复官网站点', type: Auth::CHECK, menu: false, code: 'website.site.recovery')]
    #[Logger(name: '恢复官网站点')]
    public function recovery(string $ids): array
    {
        $idArray = $this->idsOrFail($ids);
        $this->service->recovery($idArray);
        $this->success('恢复成功', $idArray);
    }

    #[DeleteMapping(path: 'real-delete/{ids}')]
    #[Auth(name: '彻底删除官网站点', type: Auth::CHECK, menu: false, code: 'website.site.real-delete')]
    #[Logger(name: '彻底删除官网站点')]
    public function realDelete(string $ids): array
    {
        $this->deleteByIds($ids, fn (array $idArray): bool => $this->service->delreal($idArray), '彻底删除成功');
    }
}
