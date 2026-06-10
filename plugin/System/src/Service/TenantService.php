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
use Library\Support\TenantContext;
use System\Mapper\TenantMapper;
use System\Model\SystemDept;
use System\Model\SystemRole;
use System\Model\SystemTenant;
use System\Model\SystemUser;
use System\Support\TenantSuperPermission;

final class TenantService extends CoreService
{
    public function __construct(
        protected TenantMapper $mapper
    ) {}

    /**
     * 创建租户并同步开通默认组织、管理员角色和管理员账号。
     *
     * 租户档案是全局管控数据；租户工作区数据写入新租户 ID，避免依赖数据库默认值落到未归属租户 0。
     */
    public function create(array $data): ?Model
    {
        $admin = $this->normalizeAdminPayload($data);

        Db::beginTransaction();
        try {
            /** @var SystemTenant $tenant */
            $tenant = $this->mapper->create($this->filterData($data));
            TenantContext::withTenant((int)$tenant->id, fn () => TenantContext::withExplicitTenantWrite(fn () => $this->provisionTenantWorkspace($tenant, $admin)));
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
        $this->assertDefaultTenantMutable($id, '禁用');

        $status = (int)_vali([
            'status.value' => $status,
            'status.integer' => '状态值必须为数字',
            'status.in:1,0' => '状态值错误',
        ])['status'];

        return $this->mapper->changeStatus($id, $status);
    }

    /**
     * 校验租户运行状态；tenant_id=0 仅表示未建立上下文，不能作为有效业务租户。
     */
    public function assertTenantAvailable(int $tenantId): void
    {
        if ($tenantId <= 0) {
            throw new ErrorResponseException('租户上下文无效');
        }

        if ($tenantId === TenantContext::DEFAULT_TENANT_ID) {
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

    public function delete(array|int $ids): bool
    {
        $this->assertDefaultTenantIdsMutable(str2arr($ids), '删除');

        return $this->mapper->delete(str2arr($ids));
    }

    public function delreal(array|int $ids): bool
    {
        $idArray = str2arr($ids);
        $this->assertDefaultTenantIdsMutable($idArray, '彻底删除');
        $this->assertTenantsHaveNoBusinessData($idArray);

        return $this->mapper->delreal($idArray);
    }

    protected function filterData(array &$data, array $exists = []): array
    {
        $isDefaultTenant = (int)($exists['id'] ?? 0) === TenantContext::DEFAULT_TENANT_ID;
        foreach (['code', 'name', 'contact_name', 'contact_phone', 'contact_email', 'package_code', 'remark'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        if ($isDefaultTenant) {
            // 默认租户是系统保留租户，只允许维护联系资料和备注，核心身份由安装/升级恢复流程固定。
            $data = array_intersect_key($data, array_flip(['contact_name', 'contact_phone', 'contact_email', 'remark']));
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
     * 租户彻底删除采用保守策略：只要任何 tenant_id 表仍存在该租户数据，就拒绝硬删并返回摘要。
     *
     * @param array<int|string, mixed> $ids
     */
    private function assertTenantsHaveNoBusinessData(array $ids): void
    {
        $tenantIds = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > TenantContext::UNSET_TENANT_ID)));
        if ($tenantIds === []) {
            return;
        }

        $repair = new TenantRepairService();
        $hits = [];
        foreach ($tenantIds as $tenantId) {
            foreach ($repair->tenantDeleteBusinessTables() as $table) {
                if ($table === 'system_tenant') {
                    continue;
                }
                $count = (int)Db::table($table)->where('tenant_id', $tenantId)->count();
                if ($count > 0) {
                    $hits[] = sprintf('tenant=%d %s:%d', $tenantId, $table, $count);
                }
                if (count($hits) >= 10) {
                    break 2;
                }
            }
        }

        if ($hits !== []) {
            throw new ErrorResponseException('租户存在业务数据，禁止彻底删除：' . implode('，', $hits));
        }
    }

    private function assertDefaultTenantIdsMutable(array $ids, string $action): void
    {
        foreach ($ids as $id) {
            $this->assertDefaultTenantMutable((int)$id, $action);
        }
    }

    private function assertDefaultTenantMutable(int $id, string $action): void
    {
        if ($id === TenantContext::DEFAULT_TENANT_ID) {
            throw new ErrorResponseException(sprintf('默认租户不允许%s', $action));
        }
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
            'admin_password.min:5' => '租户管理员初始密码至少 5 位',
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
            'super' => 1,
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
        return TenantSuperPermission::nodeIds('system.');
    }
}
