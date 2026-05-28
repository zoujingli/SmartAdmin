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
use Plugin\Website\Service\WebsiteNavService;

#[Auth(name: '官网导航管理')]
#[Controller(prefix: 'system/website/nav')]
final class SystemNavController extends CoreController
{
    public function __construct(
        protected WebsiteNavService $service
    ) {}

    #[GetMapping(path: 'index')]
    #[Auth(name: '官网导航列表', type: Auth::CHECK, menu: true, code: 'website.nav.index')]
    public function index(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getPageList($request->all()));
    }

    #[GetMapping(path: 'recycle')]
    #[Auth(name: '官网导航回收站', type: Auth::CHECK, menu: false, code: 'website.nav.index')]
    public function recycle(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getRecycleList($request->all()));
    }

    #[GetMapping(path: 'tree')]
    #[Auth(name: '官网导航树', type: Auth::CHECK, menu: false, code: 'website.nav.index')]
    public function tree(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->tree($request->all()));
    }

    #[GetMapping(path: 'info/{id}')]
    #[Auth(name: '官网导航详情', type: Auth::CHECK, menu: false, code: 'website.nav.index')]
    public function info(int $id): array
    {
        $this->respondFound($this->service->read($id));
    }

    #[GetMapping(path: 'options')]
    #[Auth(name: '官网导航选项', type: Auth::CHECK, menu: false, code: 'website.nav.index')]
    public function options(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->options($request->all()));
    }

    #[PostMapping(path: 'create')]
    #[Auth(name: '新增官网导航', type: Auth::CHECK, menu: false, code: 'website.nav.create')]
    #[Logger(name: '新增官网导航')]
    public function create(RequestInterface $request): array
    {
        $this->success('创建成功', $this->service->create($request->all()));
    }

    #[PutMapping(path: 'update/{id}')]
    #[Auth(name: '编辑官网导航', type: Auth::CHECK, menu: false, code: 'website.nav.update')]
    #[Logger(name: '编辑官网导航')]
    public function update(int $id, RequestInterface $request): array
    {
        $this->service->update($id, $request->all());
        $this->success('更新成功', $this->service->read($id));
    }

    #[PutMapping(path: 'status/{id}')]
    #[Auth(name: '更新官网导航状态', type: Auth::CHECK, menu: false, code: 'website.nav.status')]
    #[Logger(name: '更新官网导航状态')]
    public function status(int $id, RequestInterface $request): array
    {
        $this->success('更新成功', $this->service->changeStatus($id, (int)$request->input('status', 1)));
    }

    #[DeleteMapping(path: 'delete/{ids}')]
    #[Auth(name: '删除官网导航', type: Auth::CHECK, menu: false, code: 'website.nav.delete')]
    #[Logger(name: '删除官网导航')]
    public function delete(string $ids): array
    {
        $this->deleteByIds($ids, fn (array $idArray): bool => $this->service->delete($idArray));
    }

    #[PutMapping(path: 'recovery/{ids}')]
    #[Auth(name: '恢复官网导航', type: Auth::CHECK, menu: false, code: 'website.nav.recovery')]
    #[Logger(name: '恢复官网导航')]
    public function recovery(string $ids): array
    {
        $idArray = $this->idsOrFail($ids);
        $this->service->recovery($idArray);
        $this->success('恢复成功', $idArray);
    }

    #[DeleteMapping(path: 'real-delete/{ids}')]
    #[Auth(name: '彻底删除官网导航', type: Auth::CHECK, menu: false, code: 'website.nav.real-delete')]
    #[Logger(name: '彻底删除官网导航')]
    public function realDelete(string $ids): array
    {
        $this->deleteByIds($ids, fn (array $idArray): bool => $this->service->delreal($idArray), '彻底删除成功');
    }
}
