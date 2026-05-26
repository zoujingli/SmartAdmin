<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace System\Service;

use Hyperf\Database\Model\Model;
use Hyperf\DbConnection\Db;
use Library\Constants\DataScope;
use Library\Constants\Status;
use Library\CoreService;
use Library\Exception\ErrorResponseException;
use System\Mapper\TenantMapper;
use System\Model\SystemDept;
use System\Model\SystemNode;
use System\Model\SystemRole;
use System\Model\SystemTenant;
use System\Model\SystemUser;

final class TenantService extends CoreService
{
    public function __construct(
        protected TenantMapper $mapper
    ) {}

    /**
     * 创建租户并同步开通默认组织、管理员角色和管理员账号。
     *
     * 租户档案保留在平台空间；租户工作区数据写入新租户 ID，避免后续依赖当前平台上下文误落到 tenant_id=0。
     */
    public function create(array $data): ?Model
    {
        $admin = $this->normalizeAdminPayload($data);

        Db::beginTransaction();
        try {
            /** @var SystemTenant $tenant */
            $tenant = $this->mapper->create($this->filterData($data));
            $this->provisionTenantWorkspace($tenant, $admin);
            Db::commit();

            return $tenant;
        } catch (\Throwable $exception) {
            Db::rollBack();
            throw $exception;
        }
    }

    public function getStatistics(array $params = []): array
    {
        return $this->mapper->getStatistics($params);
    }

    public function getOptions(array $params = []): array
    {
        return $this->mapper->getNormalOptions($params);
    }

    /**
     * 修改租户状态。
     */
    public function changeStatus(int $id, mixed $status): bool
    {
        $status = (int)_vali([
            'status.value' => $status,
            'status.integer' => '状态值必须为数字',
            'status.in:1,0' => '状态值错误',
        ])['status'];

        return $this->mapper->changeStatus($id, $status);
    }

    /**
     * 校验租户运行状态；平台用户 tenant_id=0 直接通过。
     */
    public function assertTenantAvailable(int $tenantId): void
    {
        if ($tenantId <= 0) {
            return;
        }

        /** @var null|SystemTenant $tenant */
        $tenant = SystemTenant::query()->find($tenantId);
        if (!$tenant) {
            throw new ErrorResponseException('租户不存在');
        }

        if (!Status::isEnabled((int)$tenant->status)) {
            throw new ErrorResponseException('租户已被禁用');
        }

        $expiredAt = trim((string)($tenant->expired_at ?? ''));
        if ($expiredAt !== '' && strtotime($expiredAt) !== false && strtotime($expiredAt) < time()) {
            throw new ErrorResponseException('租户已过期');
        }
    }

    protected function filterData(array &$data, array $exists = []): array
    {
        foreach (['code', 'name', 'contact_name', 'contact_phone', 'contact_email', 'package_code', 'remark'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        if (array_key_exists('expired_at', $data) && $data['expired_at'] === '') {
            $data['expired_at'] = null;
        }

        $rules = [
            'code.filled' => '租户编码不能为空',
            'code.max:50' => '租户编码最多 50 位',
            'name.filled' => '租户名称不能为空',
            'name.max:100' => '租户名称最多 100 位',
            'contact_name.max:50' => '联系人最多 50 位',
            'contact_phone.max:30' => '联系电话最多 30 位',
            'contact_email.max:100' => '联系邮箱最多 100 位',
            'package_code.filled' => '套餐编码不能为空',
            'package_code.max:50' => '套餐编码最多 50 位',
            'expired_at.nullable' => '到期时间格式错误',
            'expired_at.date' => '到期时间格式错误',
            'status.integer' => '状态值必须为数字',
            'status.in:1,0' => '状态值错误',
            'remark.max:255' => '备注最多 255 位',
        ];
        if ($exists === []) {
            $rules['code.required'] = '租户编码不能为空';
            $rules['name.required'] = '租户名称不能为空';
            $rules['package_code.default'] = 'basic';
            $rules['status.default'] = Status::ENABLED;
        }

        $data = _vali($rules, $data);
        if (array_key_exists('status', $data)) {
            $data['status'] = (int)$data['status'];
        }

        $this->ensureUniqueField('code', $data, $exists, '租户编码已存在');
        $this->ensureUniqueField('name', $data, $exists, '租户名称已存在');

        return $data;
    }

    /**
     * 标准化开通管理员字段；新增租户必须同时提供管理员账号和初始密码。
     *
     * @return array{username:string,password:string,nickname:string,phone:string,email:string}
     */
    private function normalizeAdminPayload(array $data): array
    {
        $payload = [
            'admin_username' => $data['admin_username'] ?? '',
            'admin_password' => $data['admin_password'] ?? '',
            'admin_nickname' => $data['admin_nickname'] ?? '租户管理员',
            'admin_phone' => $data['admin_phone'] ?? ($data['contact_phone'] ?? ''),
            'admin_email' => $data['admin_email'] ?? ($data['contact_email'] ?? ''),
        ];
        foreach (['admin_username', 'admin_nickname', 'admin_phone', 'admin_email'] as $field) {
            if (is_string($payload[$field])) {
                $payload[$field] = trim($payload[$field]);
            }
        }

        $payload = _vali([
            'admin_username.required' => '租户管理员用户名不能为空',
            'admin_username.max:20' => '租户管理员用户名最多 20 位',
            'admin_password.required' => '租户管理员初始密码不能为空',
            'admin_password.min:6' => '租户管理员初始密码至少 6 位',
            'admin_password.max:255' => '租户管理员初始密码格式错误',
            'admin_nickname.max:30' => '租户管理员昵称最多 30 位',
            'admin_phone.max:11' => '租户管理员手机号最多 11 位',
            'admin_email.max:50' => '租户管理员邮箱最多 50 位',
        ], $payload);

        $username = (string)$payload['admin_username'];
        if (SystemUser::query()->withoutGlobalScope('tenant_id')->where('username', $username)->exists()) {
            throw new ErrorResponseException('租户管理员用户名已存在');
        }

        return [
            'username' => $username,
            'password' => (string)$payload['admin_password'],
            'nickname' => (string)($payload['admin_nickname'] ?: '租户管理员'),
            'phone' => (string)($payload['admin_phone'] ?? ''),
            'email' => (string)($payload['admin_email'] ?? ''),
        ];
    }

    /**
     * 初始化租户默认工作区资源。
     *
     * 默认角色使用全部数据范围；跨租户隔离由 tenant_id 全局范围兜底，租户内部仍可后续调整角色数据范围。
     *
     * @param array{username:string,password:string,nickname:string,phone:string,email:string} $admin
     */
    private function provisionTenantWorkspace(SystemTenant $tenant, array $admin): void
    {
        $tenantId = (int)$tenant->id;
        $now = date('Y-m-d H:i:s');

        $dept = SystemDept::query()->create([
            'tenant_id' => $tenantId,
            'pid' => 0,
            // 新租户默认部门同样写入稳定编码；部门编码唯一性按租户隔离，允许不同租户复用 default。
            'code' => 'default',
            'name' => '默认部门',
            'phone' => $admin['phone'],
            'email' => $admin['email'],
            'level' => '',
            'leader' => $admin['nickname'],
            'sort' => 1000,
            'status' => Status::ENABLED,
            'remark' => '租户开通时自动创建的默认部门',
            'created_by' => 0,
            'updated_by' => 0,
        ]);

        $role = SystemRole::query()->create([
            'tenant_id' => $tenantId,
            'name' => '租户管理员',
            'code' => 'tenant-admin',
            'scope' => DataScope::ALL,
            'sort' => 1000,
            'status' => Status::ENABLED,
            'remark' => '租户开通时自动创建的管理员角色',
            'created_by' => 0,
            'updated_by' => 0,
        ]);

        $user = SystemUser::query()->create([
            'tenant_id' => $tenantId,
            'username' => $admin['username'],
            'nickname' => $admin['nickname'],
            'phone' => $admin['phone'],
            'email' => $admin['email'],
            'password' => $admin['password'],
            'avatar' => '',
            'signed' => '',
            'status' => Status::ENABLED,
            'remark' => '租户开通时自动创建的管理员账号',
            'login_ip' => '',
            'login_time' => null,
            'extra' => [],
            'created_by' => 0,
            'updated_by' => 0,
        ]);

        Db::table('system_user_dept')->insert([
            'tenant_id' => $tenantId,
            'user_id' => (int)$user->id,
            'dept_id' => (int)$dept->id,
        ]);

        Db::table('system_user_role')->insert([
            'tenant_id' => $tenantId,
            'user_id' => (int)$user->id,
            'role_id' => (int)$role->id,
        ]);

        $nodeIds = $this->defaultTenantAdminNodeIds();
        foreach ($nodeIds as $nodeId) {
            Db::table('system_role_node')->insert([
                'tenant_id' => $tenantId,
                'role_id' => (int)$role->id,
                'node_id' => $nodeId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * 默认租户管理员只授予租户工作区能力，平台租户管理、系统参数、系统数据和菜单定义仍由平台管理。
     *
     * @return array<int, int>
     */
    private function defaultTenantAdminNodeIds(): array
    {
        $blockedPrefixes = [
            'system.tenant.',
            'system.menu.',
            'system.data.',
            'system.setting.',
        ];

        return SystemNode::query()
            ->where('status', Status::ENABLED)
            ->where('node', '!=', '*')
            ->get(['id', 'node'])
            ->filter(static function (SystemNode $node) use ($blockedPrefixes): bool {
                $code = (string)$node->node;
                foreach ($blockedPrefixes as $prefix) {
                    if (str_starts_with($code, $prefix)) {
                        return false;
                    }
                }

                return str_starts_with($code, 'system.');
            })
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int)$id)
            ->toArray();
    }
}
