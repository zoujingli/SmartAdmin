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
use Library\Constants\DataScope;
use Library\Constants\Status;
use Library\CoreService;
use Library\Exception\ErrorResponseException;
use Library\Exception\NotAllowResponseException;
use Library\Support\ModelChangeLog;
use System\Mapper\RoleMapper;
use System\Mapper\UserMapper;
use System\Model\SystemRole;

final class RoleService extends CoreService
{
    /**
     * @param RoleMapper $mapper 角色数据访问
     * @param AuthCacheService $authCache 鉴权缓存版本控制
     * @param MenuService $menuService 菜单与授权树服务
     * @param UserMapper $users 角色关联用户查询
     */
    public function __construct(
        protected RoleMapper $mapper,
        protected AuthCacheService $authCache,
        protected MenuService $menuService,
        protected UserMapper $users,
    ) {}

    /**
     * 创建角色基础信息（不接受内联权限字段）。
     */
    public function create(array $data): ?Model
    {
        $this->rejectInlinePermissionPayload($data);

        return parent::create($data);
    }

    /**
     * 更新角色基础信息（权限授权需走独立接口）。
     */
    public function update(mixed $id, array $data): bool
    {
        $this->rejectInlinePermissionPayload($data);

        return parent::update($id, $data);
    }

    /**
     * 删除角色。
     *
     * 删除前校验角色下是否仍有用户绑定，避免出现悬空授权关系。
     */
    public function delete(array|int $ids): bool
    {
        $ids = (array)$ids;
        $roles = $this->mapper->getOperationModels($ids);
        if (count($roles) !== count(array_values(array_unique(array_map('intval', $ids))))) {
            return false;
        }

        foreach ($roles as $role) {
            if ($role->users()->count() > 0) {
                throw new ErrorResponseException("角色 {$role->name} 下仍存在绑定用户，无法删除");
            }
        }

        $result = $this->mapper->delete($ids);
        $this->authCache->bumpGlobalVersion();

        return $result;
    }

    /**
     * 恢复角色并刷新全局权限缓存版本。
     */
    public function recovery(array|int $ids): bool
    {
        $result = $this->mapper->recovery((array)$ids);
        $this->authCache->bumpGlobalVersion();

        return $result;
    }

    /**
     * 彻底删除角色并刷新全局权限缓存版本。
     */
    public function delreal(array|int $ids): bool
    {
        $result = $this->mapper->delreal((array)$ids);
        $this->authCache->bumpGlobalVersion();

        return $result;
    }

    /**
     * 分配角色权限节点。
     *
     * 会校验当前操作者可授予范围，并记录节点变更日志。
     */
    public function assignNodes(int $id, array $nodes): array
    {
        $nodes = _vali([
            'nodes.value' => $nodes,
            'nodes.array' => '权限节点列表格式错误',
        ])['nodes'];

        /** @var SystemRole $role */
        $role = $this->mapper->read($id);
        if (!$role instanceof SystemRole) {
            throw new ErrorResponseException('角色不存在或无权限操作');
        }
        $this->assertGrantableNodes($role->getPermissionNodes());

        $oldNodes = $role->getPermissionNodes();
        $nodes = $this->normalizePermissionNodes($nodes);
        $this->assertGrantableNodes($nodes);
        $role->assignPermissionNodes($nodes);
        ModelChangeLog::recordFields($role, 'updated', [[
            'field' => 'nodes',
            'label' => '权限节点',
            'old' => $oldNodes,
            'new' => $nodes,
        ]]);
        $this->authCache->bumpGlobalVersion();

        return $role->fresh(['nodes'])->toArray();
    }

    /**
     * 获取角色已授权节点编码列表。
     */
    public function getRoleNodes(int $id): array
    {
        /** @var SystemRole $role */
        $role = $this->mapper->read($id);
        if (!$role instanceof SystemRole) {
            throw new ErrorResponseException('角色不存在或无权限访问');
        }

        $this->assertGrantableNodes($role->getPermissionNodes());

        return $role->getPermissionNodes();
    }

    /**
     * 获取角色绑定的用户列表。
     */
    public function getRoleUsers(int $id): array
    {
        /** @var SystemRole $role */
        $role = $this->mapper->read($id);
        if (!$role instanceof SystemRole) {
            throw new ErrorResponseException('角色不存在或无权限访问');
        }

        return $this->users->getUsersByRole($id);
    }

    /**
     * 获取角色下拉选项。
     */
    public function getOptions(array $params = []): array
    {
        return $this->mapper->getNormalOptions($params);
    }

    /**
     * 获取当前用户可授予的权限树。
     */
    public function getPermissionTree(): array
    {
        return $this->filterGrantableMenuTree($this->menuService->getTree());
    }

    /**
     * 获取角色统计信息。
     */
    public function getStatistics(array $params = []): array
    {
        return $this->mapper->getStatistics($params);
    }

    /**
     * 变更角色状态并触发权限缓存版本提升。
     */
    public function changeStatus(int $id, mixed $status): bool|Model|null
    {
        $status = (int)_vali([
            'status.value' => $status,
            'status.integer' => '状态值必须为数字',
            'status.in:1,0' => '状态值错误',
        ])['status'];

        $result = $this->mapper->changeStatus($id, $status);
        if ($result) {
            $this->authCache->bumpGlobalVersion();
        }

        return $result;
    }

    /**
     * 数据过滤与约束校验。
     *
     * - 新增时默认补充 scope
     * - 校验 scope 枚举合法性
     * - 校验 name/code 唯一性
     */
    protected function filterData(array &$data, array $exists = []): array
    {
        foreach (['name', 'code', 'remark'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        $rules = [
            'tenant_id.integer' => '租户 ID 必须为数字',
            'tenant_id.min:0' => '租户 ID 不能小于 0',
            'name.filled' => '角色名称不能为空',
            'name.max:100' => '角色名称最多 100 位',
            'code.filled' => '角色编码不能为空',
            'code.max:100' => '角色编码最多 100 位',
            'scope.integer' => '数据范围必须为数字',
            'scope.in:' . implode(',', array_keys(DataScope::getAll())) => '数据范围无效',
            'sort.integer' => '排序值必须为数字',
            'status.integer' => '状态值必须为数字',
            'status.in:1,0' => '状态值错误',
            'remark.max:255' => '备注最多 255 位',
        ];
        if ($exists === []) {
            $rules['name.required'] = '角色名称不能为空';
            $rules['code.required'] = '角色编码不能为空';
            $rules['scope.default'] = DataScope::getDefault();
            $rules['sort.default'] = 0;
            $rules['status.default'] = Status::ENABLED;
        }

        $data = _vali($rules, $data);
        foreach (['tenant_id', 'scope', 'sort', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = (int)$data[$field];
            }
        }

        $this->ensureUniqueField('name', $data, $exists, '角色名称已存在');
        $this->ensureUniqueField('code', $data, $exists, '角色编码已存在');

        return $data;
    }

    /**
     * 阻止通过基础新增/编辑接口直接提交权限字段。
     */
    private function rejectInlinePermissionPayload(array $data): void
    {
        // 角色基础信息和权限授权分离，避免拥有新增/编辑权限的用户绕过 system.role.assign 授权边界。
        foreach (['nodes', 'menu_ids', 'menuIds'] as $field) {
            if (array_key_exists($field, $data)) {
                throw new ErrorResponseException('角色权限请通过授权接口维护');
            }
        }
    }

    /**
     * 标准化权限节点数组：去空白、去空值、去重。
     *
     * @return array<int, string>
     */
    private function normalizePermissionNodes(array $nodes): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $node): string => trim((string)$node),
            $nodes
        ))));
    }

    /**
     * 校验当前用户是否能授予目标权限节点。
     * 非超级管理员不能授予 `*`，且目标节点必须全部属于当前用户已拥有的权限集合。
     *
     * @param array<int, string> $nodes
     */
    private function assertGrantableNodes(array $nodes): void
    {
        $nodes = $this->normalizePermissionNodes($nodes);
        if ($nodes === []) {
            return;
        }

        $currentUser = user();
        if (!$currentUser) {
            throw new NotAllowResponseException('无权限授予角色权限');
        }

        if ($currentUser->isSuper()) {
            return;
        }

        if (in_array('*', $nodes, true)) {
            throw new NotAllowResponseException('只有超级管理员可以授予全部权限');
        }

        $allowed = array_fill_keys(array_map('strval', $currentUser->getPermissions()), true);
        $denied = array_values(array_filter(
            $nodes,
            static fn (string $node): bool => !isset($allowed[$node])
        ));

        if ($denied !== []) {
            throw new NotAllowResponseException('存在不可授予的权限节点', ['denied_nodes' => $denied]);
        }
    }

    /**
     * 授权树只展示当前用户可授予的节点；父级仅在有可授予子节点时保留，用于界面保持树结构。
     *
     * @param array<int, array<string, mixed>> $tree
     * @return array<int, array<string, mixed>>
     */
    private function filterGrantableMenuTree(array $tree): array
    {
        $currentUser = user();
        if (!$currentUser) {
            return [];
        }

        if ($currentUser->isSuper()) {
            return $tree;
        }

        $allowed = array_fill_keys(array_map('strval', $currentUser->getPermissions()), true);
        if (isset($allowed['*'])) {
            return $tree;
        }

        return $this->filterMenuTreeByPermissions($tree, $allowed);
    }

    /**
     * 按当前用户权限裁剪菜单树，仅保留可授予节点及其必要父级结构。
     *
     * @param array<int, array<string, mixed>> $tree
     * @param array<string, bool> $allowed
     * @return array<int, array<string, mixed>>
     */
    private function filterMenuTreeByPermissions(array $tree, array $allowed): array
    {
        $result = [];
        foreach ($tree as $item) {
            $children = is_array($item['children'] ?? null) ? $item['children'] : [];
            $item['children'] = $this->filterMenuTreeByPermissions($children, $allowed);
            $code = trim((string)($item['code'] ?? ''));
            $canGrant = $code !== '' && isset($allowed[$code]);
            if (!$canGrant) {
                $item['code'] = '';
            }

            if ($item['children'] !== [] || $canGrant) {
                $result[] = $item;
            }
        }

        return $result;
    }
}
