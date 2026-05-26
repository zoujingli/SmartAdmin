<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace System\Controller;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\DeleteMapping;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Annotation\PutMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Library\Constants\Status;
use Library\CoreController;
use Library\Events\Annotation\Auth;
use Library\Events\Annotation\Logger;
use System\Service\PasswordCryptoService;
use System\Service\UserService;

#[Auth(name: '系统用户管理')]
#[Controller(prefix: 'system/user')]
final class UserController extends CoreController
{
    /**
     * @param UserService $service 用户业务服务
     * @param PasswordCryptoService $passwordCrypto 密码传输层解密服务
     */
    public function __construct(
        protected UserService $service,
        protected PasswordCryptoService $passwordCrypto,
    ) {}

    /**
     * 获取用户分页列表。
     */
    #[GetMapping(path: 'index')]
    #[Auth(name: '系统用户列表', type: Auth::CHECK, menu: true)]
    public function index(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getPageList($request->all()));
    }

    /**
     * 获取用户回收站列表。
     */
    #[GetMapping(path: 'recycle')]
    #[Auth(name: '系统用户管理', type: Auth::CHECK, menu: false, code: 'system.user.index')]
    public function recycle(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getRecycleList($request->all()));
    }

    /**
     * 获取用户详情（含关联关系）。
     */
    #[GetMapping(path: 'info/{id}')]
    #[Auth(name: '系统用户详情', type: Auth::CHECK, menu: false, code: 'system.user.index')]
    public function info(int $id): array
    {
        $this->respondFound($this->service->getUserWithRelations($id));
    }

    /**
     * 创建用户。
     */
    #[PostMapping(path: 'create')]
    #[Auth(name: '新增系统用户', type: Auth::CHECK, menu: false)]
    #[Logger(name: '新增系统用户', excludeFields: ['password'])]
    public function create(RequestInterface $request): array
    {
        $data = $this->passwordCrypto->decryptFields($request->all(), [
            'password' => PasswordCryptoService::PURPOSE_USER_CREATE_PASSWORD,
        ]);

        $this->success('创建成功', $this->service->create($data));
    }

    /**
     * 更新用户。
     */
    #[PutMapping(path: 'update/{id}')]
    #[Auth(name: '编辑系统用户', type: Auth::CHECK, menu: false)]
    #[Logger(name: '编辑系统用户', excludeFields: ['password'])]
    public function update(int $id, RequestInterface $request): array
    {
        $data = $this->passwordCrypto->decryptFields($request->all(), [
            'password' => PasswordCryptoService::PURPOSE_USER_UPDATE_PASSWORD,
        ]);
        $this->service->update($id, $data);
        $this->success('更新成功', $this->service->read($id));
    }

    /**
     * 删除用户（软删）。
     */
    #[DeleteMapping(path: 'delete/{ids}')]
    #[Auth(name: '删除系统用户', type: Auth::CHECK, menu: false)]
    #[Logger(name: '删除系统用户')]
    public function delete(string $ids): array
    {
        $this->deleteByIds($ids, fn (array $idArray) => $this->service->delete($idArray));
    }

    /**
     * 彻底删除用户。
     */
    #[DeleteMapping(path: 'real-delete/{ids}')]
    #[Auth(name: '彻底删除系统用户', type: Auth::CHECK, menu: false)]
    #[Logger(name: '彻底删除系统用户')]
    public function realDelete(string $ids): array
    {
        $this->deleteByIds($ids, fn (array $idArray) => $this->service->delreal($idArray), '彻底删除成功');
    }

    /**
     * 恢复用户。
     */
    #[PutMapping(path: 'recovery/{ids}')]
    #[Auth(name: '恢复系统用户', type: Auth::CHECK, menu: false)]
    #[Logger(name: '恢复系统用户')]
    public function recovery(string $ids): array
    {
        $idArray = $this->idsOrFail($ids);
        $this->service->recovery($idArray);
        $this->success('恢复成功', $idArray);
    }

    /**
     * 更新用户状态。
     */
    #[PutMapping(path: 'status/{id}')]
    #[Auth(name: '更新用户状态', type: Auth::CHECK, menu: false, code: 'system.user.update')]
    #[Logger(name: '更新用户状态')]
    public function changeStatus(int $id, RequestInterface $request): array
    {
        $status = $request->input('status', Status::ENABLED);
        $this->success('更新成功', $this->service->changeStatus($id, $status));
    }

    /**
     * 更新用户排序。
     */
    #[PutMapping(path: 'sort/{id}')]
    #[Auth(name: '更新用户排序', type: Auth::CHECK, menu: false, code: 'system.user.update')]
    #[Logger(name: '更新用户排序')]
    public function changeSort(int $id, RequestInterface $request): array
    {
        $sort = $request->input('sort', 0);
        $this->success('更新成功', $this->service->changeSort($id, $sort));
    }

    /**
     * 重置用户密码。
     */
    #[PutMapping(path: 'reset-password/{id}')]
    #[Auth(name: '重置用户密码', type: Auth::CHECK, menu: false)]
    #[Logger(name: '重置用户密码', excludeFields: ['password'])]
    public function resetPassword(int $id, RequestInterface $request): array
    {
        $data = $this->passwordCrypto->decryptFields($request->all(), [
            'password' => PasswordCryptoService::PURPOSE_USER_RESET_PASSWORD,
        ]);
        $password = (string)($data['password'] ?? '');
        $this->success('密码重置成功', $this->service->changePassword($id, $password));
    }

    /**
     * 分配用户角色。
     */
    #[PutMapping(path: 'roles/{id}')]
    #[Auth(name: '分配用户角色', type: Auth::CHECK, menu: false, code: 'system.user.update')]
    #[Logger(name: '分配用户角色')]
    public function assignRoles(int $id, RequestInterface $request): array
    {
        $roleIds = $request->all()['role_ids'] ?? [];
        $this->success('角色分配成功', $this->service->assignRoles($id, $roleIds));
    }

    /**
     * 分配用户部门。
     */
    #[PutMapping(path: 'depts/{id}')]
    #[Auth(name: '分配用户部门', type: Auth::CHECK, menu: false, code: 'system.user.update')]
    #[Logger(name: '分配用户部门')]
    public function assignDepts(int $id, RequestInterface $request): array
    {
        $deptIds = $request->all()['dept_ids'] ?? [];
        $this->success('部门分配成功', $this->service->assignDepts($id, $deptIds));
    }

    /**
     * 分配用户岗位。
     */
    #[PutMapping(path: 'posts/{id}')]
    #[Auth(name: '分配用户岗位', type: Auth::CHECK, menu: false, code: 'system.user.update')]
    #[Logger(name: '分配用户岗位')]
    public function assignPosts(int $id, RequestInterface $request): array
    {
        $postIds = $request->all()['post_ids'] ?? [];
        $this->success('岗位分配成功', $this->service->assignPosts($id, $postIds));
    }

    /**
     * 获取用户角色列表。
     */
    #[GetMapping(path: 'roles/{id}')]
    #[Auth(name: '获取用户角色', type: Auth::CHECK, menu: false, code: 'system.user.update')]
    public function getRoles(int $id): array
    {
        $this->success('获取成功', $this->service->getUserRoles($id));
    }

    /**
     * 获取用户部门列表。
     */
    #[GetMapping(path: 'depts/{id}')]
    #[Auth(name: '获取用户部门', type: Auth::CHECK, menu: false, code: 'system.user.update')]
    public function getDepts(int $id): array
    {
        $this->success('获取成功', $this->service->getUserDepts($id));
    }

    /**
     * 获取用户岗位列表。
     */
    #[GetMapping(path: 'posts/{id}')]
    #[Auth(name: '获取用户岗位', type: Auth::CHECK, menu: false, code: 'system.user.update')]
    public function getPosts(int $id): array
    {
        $this->success('获取成功', $this->service->getUserPosts($id));
    }

    /**
     * 获取用户统计信息。
     */
    #[GetMapping(path: 'statistics')]
    #[Auth(name: '用户统计', type: Auth::CHECK, menu: false, code: 'system.user.index')]
    public function statistics(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getStatistics($request->all()));
    }

    /**
     * 获取用户选项列表。
     */
    #[GetMapping(path: 'options')]
    #[Auth(name: '用户选项', type: Auth::CHECK, menu: false, code: 'system.user.index')]
    public function options(RequestInterface $request): array
    {
        $this->success('获取成功', $this->service->getOptions($request->all()));
    }
}
