<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://github.com/zoujingli/SmartAdmin/blob/master/readme.md
 */

namespace System\Controller;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\DeleteMapping;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Annotation\PutMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Library\CoreController;
use Library\Events\Annotation\Auth;
use Library\Events\Annotation\Logger;
use System\Service\PasswordCryptoService;
use System\Service\TenantService;

#[Auth(name: '租户管理')]
#[Controller(prefix: 'system/tenant')]
final class TenantController extends CoreController
{
    public function __construct(
        protected TenantService $service,
        protected PasswordCryptoService $passwordCrypto,
    ) {}

    #[GetMapping(path: 'index')]
    #[Auth(name: '租户列表', type: Auth::CHECK, menu: true, code: 'system.tenant.index')]
    public function index(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getPageList($request->all()));
    }

    #[GetMapping(path: 'recycle')]
    #[Auth(name: '租户回收站', type: Auth::CHECK, menu: false, code: 'system.tenant.index')]
    public function recycle(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getRecycleList($request->all()));
    }

    #[GetMapping(path: 'info/{id}')]
    #[Auth(name: '租户详情', type: Auth::CHECK, menu: false, code: 'system.tenant.index')]
    public function info(int $id): array
    {
        $this->respondFound($this->service->read($id));
    }

    #[PostMapping(path: 'create')]
    #[Auth(name: '新增租户', type: Auth::CHECK, menu: false, code: 'system.tenant.create')]
    #[Logger(name: '新增租户', excludeFields: ['admin_password'])]
    public function create(RequestInterface $request): array
    {
        $data = $this->passwordCrypto->decryptFields($request->all(), [
            'admin_password' => PasswordCryptoService::PURPOSE_USER_CREATE_PASSWORD,
        ]);

        $this->success('创建成功', $this->service->create($data));
    }

    #[PutMapping(path: 'update/{id}')]
    #[Auth(name: '编辑租户', type: Auth::CHECK, menu: false, code: 'system.tenant.update')]
    #[Logger(name: '编辑租户')]
    public function update(int $id, RequestInterface $request): array
    {
        if (!$this->service->update($id, $request->all())) {
            $this->error('更新失败');
        }

        $this->success('更新成功', $this->service->read($id));
    }

    #[DeleteMapping(path: 'delete/{ids}')]
    #[Auth(name: '删除租户', type: Auth::CHECK, menu: false, code: 'system.tenant.delete')]
    #[Logger(name: '删除租户')]
    public function delete(string $ids): array
    {
        $this->deleteByIds($ids, fn (array $idArray) => $this->service->delete($idArray));
    }

    #[DeleteMapping(path: 'real-delete/{ids}')]
    #[Auth(name: '彻底删除租户', type: Auth::CHECK, menu: false, code: 'system.tenant.real-delete')]
    #[Logger(name: '彻底删除租户')]
    public function realDelete(string $ids): array
    {
        $this->deleteByIds($ids, fn (array $idArray) => $this->service->delreal($idArray), '删除成功');
    }

    #[PutMapping(path: 'recovery/{ids}')]
    #[Auth(name: '恢复租户', type: Auth::CHECK, menu: false, code: 'system.tenant.recovery')]
    #[Logger(name: '恢复租户')]
    public function recovery(string $ids): array
    {
        $idArray = $this->idsOrFail($ids);
        $this->success('恢复成功', $this->service->recovery($idArray));
    }

    #[PutMapping(path: 'status/{id}')]
    #[Auth(name: '更新租户状态', type: Auth::CHECK, menu: false, code: 'system.tenant.update')]
    #[Logger(name: '更新租户状态')]
    public function changeStatus(int $id, RequestInterface $request): array
    {
        $status = $request->input('status', 1);
        $this->success('更新成功', $this->service->changeStatus($id, $status));
    }

    #[GetMapping(path: 'statistics')]
    #[Auth(name: '租户统计', type: Auth::CHECK, menu: false, code: 'system.tenant.index')]
    public function statistics(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getStatistics($request->all()));
    }

    #[GetMapping(path: 'options')]
    #[Auth(name: '租户选项', type: Auth::CHECK, menu: false, code: 'system.tenant.index')]
    public function options(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getOptions($request->all()));
    }

}
