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
use Plugin\Website\Service\WebsiteAppService;
use Plugin\Website\Support\WebsiteOpenApiScope;

#[Auth(name: '官网接口应用管理')]
#[Controller(prefix: 'system/website/app')]
final class SystemAppController extends CoreController
{
    public function __construct(
        protected WebsiteAppService $service
    ) {}

    #[GetMapping(path: 'index')]
    #[Auth(name: '官网接口应用列表', type: Auth::CHECK, menu: true, code: 'website.app.index')]
    public function index(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getPageList($request->all()));
    }

    #[GetMapping(path: 'info/{id}')]
    #[Auth(name: '官网接口应用详情', type: Auth::CHECK, menu: false, code: 'website.app.index')]
    public function info(int $id): array
    {
        $this->success('获取成功', $this->service->detail($id));
    }

    #[GetMapping(path: 'scope-options')]
    #[Auth(name: '官网接口权限选项', type: Auth::CHECK, menu: false, code: 'website.app.index')]
    public function scopeOptions(): array
    {
        $this->success('获取成功', WebsiteOpenApiScope::options());
    }

    #[PostMapping(path: 'create')]
    #[Auth(name: '新增官网接口应用', type: Auth::CHECK, menu: false, code: 'website.app.create')]
    #[Logger(name: '新增官网接口应用', excludeFields: ['app_key'])]
    public function create(RequestInterface $request): array
    {
        $this->success('创建成功，请立即保存 AppKey，离开后无法再次查看明文。', $this->service->createWithSecret($request->all()));
    }

    #[PutMapping(path: 'update/{id}')]
    #[Auth(name: '编辑官网接口应用', type: Auth::CHECK, menu: false, code: 'website.app.update')]
    #[Logger(name: '编辑官网接口应用', excludeFields: ['app_key'])]
    public function update(int $id, RequestInterface $request): array
    {
        $this->service->update($id, $request->all());
        $this->success('更新成功', $this->service->detail($id));
    }

    #[PutMapping(path: 'reset-key/{id}')]
    #[Auth(name: '重置官网接口密钥', type: Auth::CHECK, menu: false, code: 'website.app.reset-key')]
    #[Logger(name: '重置官网接口密钥', excludeFields: ['app_key'])]
    public function resetKey(int $id): array
    {
        $this->success('重置成功，请立即保存新 AppKey，旧密钥已失效。', $this->service->resetKey($id));
    }

    #[PutMapping(path: 'status/{id}')]
    #[Auth(name: '更新官网接口应用状态', type: Auth::CHECK, menu: false, code: 'website.app.status')]
    #[Logger(name: '更新官网接口应用状态')]
    public function status(int $id, RequestInterface $request): array
    {
        $this->success('更新成功', $this->service->changeStatus($id, (int)$request->input('status', 1)));
    }

    #[DeleteMapping(path: 'delete/{ids}')]
    #[Auth(name: '删除官网接口应用', type: Auth::CHECK, menu: false, code: 'website.app.delete')]
    #[Logger(name: '删除官网接口应用')]
    public function delete(string $ids): array
    {
        $this->deleteByIds($ids, fn (array $idArray): bool => $this->service->delete($idArray));
    }

    #[PutMapping(path: 'recovery/{ids}')]
    #[Auth(name: '恢复官网接口应用', type: Auth::CHECK, menu: false, code: 'website.app.recovery')]
    #[Logger(name: '恢复官网接口应用')]
    public function recovery(string $ids): array
    {
        $idArray = $this->idsOrFail($ids);
        $this->service->recovery($idArray);
        $this->success('恢复成功', $idArray);
    }

    #[DeleteMapping(path: 'real-delete/{ids}')]
    #[Auth(name: '彻底删除官网接口应用', type: Auth::CHECK, menu: false, code: 'website.app.real-delete')]
    #[Logger(name: '彻底删除官网接口应用')]
    public function realDelete(string $ids): array
    {
        $this->deleteByIds($ids, fn (array $idArray): bool => $this->service->delreal($idArray), '彻底删除成功');
    }
}
