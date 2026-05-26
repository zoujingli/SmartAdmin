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
use Library\Constants\MenuType;
use Library\Constants\Status;
use Library\CoreService;
use Library\Exception\ErrorResponseException;
use Library\Helper\ArrayTreeHelper;
use Library\Helper\HierarchyLevelHelper;
use Library\Interfaces\NodeNameResolverInterface;
use System\Mapper\MenuMapper;
use System\Model\SystemMenu;
use System\Model\SystemUser;
use System\Support\SystemMenuFormatter;

final class MenuService extends CoreService implements NodeNameResolverInterface
{
    /**
     * @param MenuMapper $mapper 菜单数据访问层
     */
    public function __construct(
        protected MenuMapper $mapper
    ) {}

    /**
     * 创建菜单并生成层级路径。
     */
    public function create(array $data): ?Model
    {
        $data = $this->normalizeMenuPayload($data);
        $data['pid'] = (int)_vali([
            'pid.default' => 0,
            'pid.integer' => '上级菜单必须为数字',
            'pid.min:0' => '上级菜单不能小于 0',
        ], $data)['pid'];
        if ($data['pid'] > 0 && !$this->mapper->read($data['pid'])) {
            throw new ErrorResponseException('父级菜单不存在');
        }

        $data['level'] = HierarchyLevelHelper::resolveLevel(SystemMenu::class, $data['pid']);

        return parent::create($data);
    }

    /**
     * 读取菜单并补充格式化字段。
     */
    public function read(mixed $id, array $column = ['*']): ?Model
    {
        $menu = $this->mapper->read($id, $column);
        if (!$menu instanceof SystemMenu) {
            return $menu;
        }

        return SystemMenuFormatter::decorateModel($menu);
    }

    /**
     * 更新菜单并维护树层级一致性。
     */
    public function update(mixed $id, array $data): bool
    {
        $data = $this->normalizeMenuPayload($data);

        if (array_key_exists('pid', $data)) {
            $data['pid'] = (int)_vali([
                'pid.integer' => '上级菜单必须为数字',
                'pid.min:0' => '上级菜单不能小于 0',
            ], ['pid' => $data['pid']])['pid'];
        }

        if (array_key_exists('pid', $data) && $data['pid'] === (int)$id) {
            throw new ErrorResponseException('菜单不能选择自己作为上级');
        }

        if (array_key_exists('pid', $data) && $data['pid'] > 0 && !$this->mapper->read($data['pid'])) {
            throw new ErrorResponseException('父级菜单不存在');
        }

        if (array_key_exists('pid', $data) && HierarchyLevelHelper::isDescendantOf(SystemMenu::class, (int)$id, $data['pid'])) {
            throw new ErrorResponseException('菜单不能移动到自己的子级下面');
        }

        if (array_key_exists('pid', $data)) {
            $data['level'] = HierarchyLevelHelper::resolveLevel(SystemMenu::class, $data['pid']);
        }

        $result = parent::update($id, $data);

        if (array_key_exists('pid', $data)) {
            HierarchyLevelHelper::refreshDescendantLevels(SystemMenu::class, (int)$id);
        }

        return $result;
    }

    /**
     * 通过权限节点编码解析菜单名称。
     */
    public function findNameByNode(string $node): string
    {
        $menu = $this->mapper->findByField('code', $node);
        if ($menu instanceof SystemMenu) {
            return (string)$menu->name;
        }

        return '未命名菜单';
    }

    /**
     * 获取菜单分页列表。
     */
    public function getPageList(?array $params = null, bool $isScope = true, string $pageName = 'page'): array
    {
        return $this->mapper->getPageList($params, $isScope, $pageName);
    }

    /**
     * 获取管理端菜单树。
     */
    public function getTree(array $params = []): array
    {
        $conditions = [];

        if (array_key_exists('type', $params)) {
            $conditions['type'] = $this->normalizeMenuType($params['type']);
        }

        if (array_key_exists('status', $params)) {
            $conditions['status'] = (int)$params['status'];
        }

        if (array_key_exists('pid', $params)) {
            $conditions['pid'] = (int)$params['pid'];
        }

        return SystemMenuFormatter::toAdminTree($this->mapper->getTree($conditions));
    }

    /**
     * 删除菜单（存在子菜单时拒绝删除）。
     */
    public function delete(array|int $ids): bool
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array)$ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return false;
        }

        foreach ($ids as $id) {
            $menu = $this->mapper->read($id);
            if (!$menu instanceof SystemMenu) {
                return false;
            }

            if ($menu->hasChildren()) {
                throw new ErrorResponseException("菜单 {$menu->name} 下仍存在子菜单，无法删除");
            }
        }

        return $this->mapper->delete($ids);
    }

    /**
     * 获取菜单下拉树选项。
     */
    public function getOptions(): array
    {
        return ArrayTreeHelper::build($this->mapper->getOptions());
    }

    /**
     * 获取当前用户运行菜单树。
     */
    public function getUserMenus(): array
    {
        return SystemMenuFormatter::toFrontendTree($this->buildUserMenuTree());
    }

    /**
     * 解析当前用户首页路径。
     */
    public function getUserHomePath(): string
    {
        return $this->resolveFrontendHomePath($this->getUserMenus());
    }

    /**
     * 获取当前用户按钮权限列表。
     */
    public function getPermissions(): array
    {
        $permissions = $this->mapper->getButtonPermissions();
        $user = $this->currentMenuUser();
        if (!$user) {
            return [];
        }

        if (!$user->isSuper()) {
            $allowed = array_fill_keys(array_map('strval', $user->getPermissions()), true);
            if (!isset($allowed['*'])) {
                $permissions = array_values(array_filter(
                    $permissions,
                    static fn (array $menu): bool => isset($allowed[(string)($menu['code'] ?? '')])
                ));
            }
        }

        return array_map(static fn (array $menu): array => [
            'id' => (int)($menu['id'] ?? 0),
            'name' => (string)($menu['name'] ?? ''),
            'code' => (string)($menu['code'] ?? ''),
            'route' => (string)($menu['route'] ?? ''),
        ], $permissions);
    }

    /**
     * 获取可用于菜单权限标识输入提示的后台注解节点。
     *
     * 返回值只作为输入建议，菜单仍允许保存未注册的自定义权限码。
     *
     * @return array<int, array{value:string,label:string,node:string,name:string}>
     */
    public function getNodeOptions(array $params = []): array
    {
        return $this->mapper->getNodeOptions($params);
    }

    /**
     * 获取菜单统计信息。
     */
    public function getStatistics(array $params = []): array
    {
        return $this->mapper->getStatistics($params);
    }

    /**
     * 修改菜单状态。
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
     * 修改菜单排序。
     */
    public function changeSort(int $id, mixed $sort): bool
    {
        $sort = (int)_vali([
            'sort.value' => $sort,
            'sort.integer' => '排序值必须为数字',
        ])['sort'];

        return $this->mapper->changeSort($id, $sort);
    }

    /**
     * 菜单写入前统一校验与格式化。
     */
    protected function filterData(array &$data, array $exists = []): array
    {
        $data = $this->normalizeMenuPayload($data);
        foreach (['level', 'name', 'code', 'icon', 'type', 'route', 'component', 'redirect', 'link', 'iframe_src', 'remark'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        $rules = [
            'pid.integer' => '上级菜单必须为数字',
            'pid.min:0' => '上级菜单不能小于 0',
            'level.max:255' => '层级路径最多 255 位',
            'name.filled' => '菜单名称不能为空',
            'name.max:50' => '菜单名称最多 50 位',
            'code.max:100' => '菜单权限标识最多 100 位',
            'icon.max:50' => '菜单图标最多 50 位',
            'type.in:' . implode(',', array_keys(MenuType::getAll())) => '菜单类型错误',
            'route.max:200' => '菜单路由最多 200 位',
            'component.max:255' => '菜单组件最多 255 位',
            'redirect.max:255' => '重定向地址最多 255 位',
            'link.max:255' => '外链地址最多 255 位',
            'iframe_src.max:255' => '内嵌地址最多 255 位',
            'hide_in_menu.integer' => '隐藏菜单标记必须为数字',
            'hide_in_menu.in:1,0' => '隐藏菜单标记错误',
            'hide_in_breadcrumb.integer' => '隐藏面包屑标记必须为数字',
            'hide_in_breadcrumb.in:1,0' => '隐藏面包屑标记错误',
            'hide_in_tab.integer' => '隐藏标签页标记必须为数字',
            'hide_in_tab.in:1,0' => '隐藏标签页标记错误',
            'keep_alive.integer' => '缓存组件标记必须为数字',
            'keep_alive.in:1,0' => '缓存组件标记错误',
            'affix_tab.integer' => '固定标签页标记必须为数字',
            'affix_tab.in:1,0' => '固定标签页标记错误',
            'sort.integer' => '排序值必须为数字',
            'status.integer' => '状态值必须为数字',
            'status.in:1,0' => '状态值错误',
            'remark.max:255' => '备注最多 255 位',
        ];
        if ($exists === []) {
            $rules['name.required'] = '菜单名称不能为空';
            $rules['type.default'] = MenuType::MENU;
            $rules['pid.default'] = 0;
            $rules['sort.default'] = 0;
            $rules['status.default'] = Status::ENABLED;
        }

        $data = _vali($rules, $data);
        foreach (['pid', 'hide_in_menu', 'hide_in_breadcrumb', 'hide_in_tab', 'keep_alive', 'affix_tab', 'sort', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = (int)$data[$field];
            }
        }

        $this->ensureUniqueField('code', $data, $exists, '菜单权限标识已存在');
        $this->ensureUniqueField('route', $data, $exists, '菜单路由已存在');

        return $data;
    }

    /**
     * 兼容界面字段命名并规范化菜单入参。
     */
    private function normalizeMenuPayload(array $data): array
    {
        if (array_key_exists('parentId', $data) && !array_key_exists('pid', $data)) {
            $data['pid'] = (int)$data['parentId'];
        }

        if (array_key_exists('path', $data) && !array_key_exists('route', $data)) {
            $data['route'] = (string)$data['path'];
        }

        if (array_key_exists('permission', $data) && !array_key_exists('code', $data)) {
            $data['code'] = (string)$data['permission'];
        }

        $camelToSnake = [
            'iframeSrc' => 'iframe_src',
            'hideInMenu' => 'hide_in_menu',
            'hideInBreadcrumb' => 'hide_in_breadcrumb',
            'hideInTab' => 'hide_in_tab',
            'keepAlive' => 'keep_alive',
            'affixTab' => 'affix_tab',
        ];
        foreach ($camelToSnake as $camel => $snake) {
            if (array_key_exists($camel, $data) && !array_key_exists($snake, $data)) {
                $data[$snake] = $data[$camel];
            }
        }

        if (array_key_exists('type', $data) && is_scalar($data['type'])) {
            $data['type'] = MenuType::isValid($data['type'])
                ? $this->normalizeMenuType($data['type'])
                : strtoupper(trim((string)$data['type']));
        }

        foreach (['hide_in_menu', 'hide_in_breadcrumb', 'hide_in_tab', 'keep_alive', 'affix_tab'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->toFlag($data[$field]);
            }
        }

        return $data;
    }

    /**
     * 规范化菜单类型。
     */
    private function normalizeMenuType(mixed $type): string
    {
        return MenuType::normalize($type);
    }

    /**
     * 将布尔输入转换为 0/1 标记位。
     */
    private function toFlag(mixed $value): mixed
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_int($value)) {
            return in_array($value, [0, 1], true) ? $value : $value;
        }

        if (!is_scalar($value)) {
            return $value;
        }

        return match (strtolower(trim((string)$value))) {
            '1', 'true', 'yes', 'on' => 1,
            '0', 'false', 'no', 'off', '' => 0,
            default => $value,
        };
    }

    /**
     * 获取启用状态的界面菜单原始数据。
     */
    private function getEnabledFrontendRows(): array
    {
        return SystemMenu::query()
            ->where('status', Status::ENABLED)
            ->whereNotIn('type', MenuType::getQueryValues(MenuType::BUTTON))
            ->orderBy('sort', 'desc')
            ->orderBy('id', 'asc')
            ->get()
            ->toArray();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildUserMenuTree(): array
    {
        $user = $this->currentMenuUser();
        if (!$user) {
            return [];
        }

        if ($user->isSuper()) {
            $rows = $this->getEnabledFrontendRows();
        } else {
            $rows = $this->mapper->getMenusByUser((int)$user->id);
        }

        if (!$user->isSuper()) {
            $permissions = $user->getPermissions();
            if ($permissions !== [] && !in_array('*', $permissions, true)) {
                $rows = array_values(array_filter($rows, static function (array $menu) use ($permissions): bool {
                    $code = (string)($menu['code'] ?? '');
                    return $code === '' || in_array($code, $permissions, true);
                }));
            }
        }

        return $this->normalizeUserMenuTree(ArrayTreeHelper::build($rows));
    }

    /**
     * 解析当前菜单请求用户。
     */
    private function currentMenuUser(): ?SystemUser
    {
        $user = user(SystemUser::class);

        return $user instanceof SystemUser ? $user : null;
    }

    /**
     * @param array<int, array<string, mixed>> $menus
     * @return array<int, array<string, mixed>>
     */
    private function normalizeUserMenuTree(array $menus): array
    {
        return array_map(function (array $menu): array {
            $children = !empty($menu['children']) && is_array($menu['children'])
                ? $this->normalizeUserMenuTree($menu['children'])
                : [];

            $menu['children'] = $children;

            if (MenuType::normalize($menu['type'] ?? MenuType::MENU) === MenuType::PATH) {
                $menu['redirect'] = $this->resolvePreferredRedirect($children);
            }

            return $menu;
        }, $menus);
    }

    /**
     * @param array<int, array<string, mixed>> $menus
     */
    private function resolvePreferredRedirect(array $menus): string
    {
        foreach ([false, true] as $includeHidden) {
            foreach ($menus as $menu) {
                if (!$includeHidden && !empty($menu['hide_in_menu'])) {
                    continue;
                }

                $type = MenuType::normalize($menu['type'] ?? MenuType::MENU);
                $route = trim((string)($menu['route'] ?? ''));
                if ($type !== MenuType::PATH && $route !== '') {
                    return $route;
                }

                if (!empty($menu['redirect'])) {
                    return trim((string)$menu['redirect']);
                }

                if (!empty($menu['children']) && is_array($menu['children'])) {
                    $childRoute = $this->resolvePreferredRedirect($menu['children']);
                    if ($childRoute !== '') {
                        return $childRoute;
                    }
                }
            }
        }

        return '';
    }

    /**
     * @param array<int, array<string, mixed>> $menus
     */
    private function resolveFrontendHomePath(array $menus): string
    {
        foreach ($menus as $menu) {
            $redirect = trim((string)($menu['redirect'] ?? ''));
            if ($redirect !== '') {
                return $redirect;
            }

            $type = MenuType::normalize($menu['typeCode'] ?? $menu['type'] ?? MenuType::MENU);
            $path = trim((string)($menu['path'] ?? ''));
            if ($type !== MenuType::PATH && $path !== '') {
                return $path;
            }

            $children = $menu['children'] ?? [];
            if (is_array($children) && $children !== []) {
                $childPath = $this->resolveFrontendHomePath($children);
                if ($childPath !== '') {
                    return $childPath;
                }
            }
        }

        return '/';
    }
}
