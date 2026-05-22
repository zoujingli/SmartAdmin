<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://github.com/zoujingli/SmartAdmin/blob/master/readme.md
 */

namespace System\Service;

use Hyperf\Database\Model\Model;
use Library\Constants\Status;
use Library\CoreService;
use Library\Exception\ErrorResponseException;
use Library\Helper\HierarchyLevelHelper;
use System\Mapper\DeptMapper;
use System\Mapper\UserMapper;
use System\Model\SystemDept;

final class DeptService extends CoreService
{
    /**
     * @param DeptMapper $mapper 部门数据访问层
     * @param UserMapper $users 用户数据访问层
     */
    public function __construct(
        protected DeptMapper $mapper,
        protected UserMapper $users
    ) {}

    /**
     * 获取部门树。
     */
    public function getTree(array $params = []): array
    {
        return $this->mapper->getTree($params);
    }

    /**
     * 创建部门并生成层级路径。
     */
    public function create(array $data): ?Model
    {
        $data['pid'] = (int)_vali([
            'pid.default' => 0,
            'pid.integer' => '上级部门必须为数字',
            'pid.min:0' => '上级部门不能小于 0',
        ], $data)['pid'];

        if ($data['pid'] > 0 && !$this->mapper->read($data['pid'])) {
            throw new ErrorResponseException('父级部门不存在');
        }

        $data['level'] = HierarchyLevelHelper::resolveLevel(SystemDept::class, $data['pid']);

        return parent::create($data);
    }

    /**
     * 更新部门并维护层级路径一致性。
     */
    public function update(mixed $id, array $data): bool
    {
        /** @var null|SystemDept $dept */
        $dept = $this->mapper->read($id);
        if (!$dept) {
            throw new ErrorResponseException('部门不存在');
        }

        if (array_key_exists('pid', $data)) {
            $data['pid'] = (int)_vali([
                'pid.integer' => '上级部门必须为数字',
                'pid.min:0' => '上级部门不能小于 0',
            ], ['pid' => $data['pid']])['pid'];

            if ($data['pid'] === (int)$id) {
                throw new ErrorResponseException('不能将自己设为父级部门');
            }

            if ($data['pid'] > 0 && !$this->mapper->read($data['pid'])) {
                throw new ErrorResponseException('父级部门不存在');
            }

            if (HierarchyLevelHelper::isDescendantOf(SystemDept::class, (int)$id, $data['pid'])) {
                throw new ErrorResponseException('不能将部门设为子部门的子部门');
            }

            $data['level'] = HierarchyLevelHelper::resolveLevel(SystemDept::class, $data['pid']);
        }

        $result = parent::update($id, $data);

        if (array_key_exists('pid', $data)) {
            HierarchyLevelHelper::refreshDescendantLevels(SystemDept::class, (int)$id);
        }

        return $result;
    }

    /**
     * 删除部门（存在子部门或用户时禁止删除）。
     */
    public function delete(array|int $ids): bool
    {
        $ids = array_values(array_filter(array_map(static fn ($id): int => (int)$id, (array)$ids)));

        foreach ($ids as $id) {
            $dept = $this->mapper->read($id);
            if (!$dept) {
                throw new ErrorResponseException('部门不存在');
            }

            if ($this->mapper->hasChildren($id)) {
                throw new ErrorResponseException("部门 {$dept->name} 下还有子部门，无法删除");
            }

            if ($this->mapper->hasUsers($id)) {
                throw new ErrorResponseException("部门 {$dept->name} 下还有用户，无法删除");
            }
        }

        return $this->mapper->delete($ids);
    }

    /**
     * 获取部门下拉选项。
     */
    public function getOptions(array $params = []): array
    {
        return $this->mapper->getOptions($params);
    }

    /**
     * 获取部门下用户列表。
     */
    public function getDeptUsers(int $id): array
    {
        if (!$this->mapper->read($id)) {
            throw new ErrorResponseException('部门不存在或无权限访问');
        }

        return $this->users->getUsersByDept($id);
    }

    /**
     * 获取部门统计信息。
     */
    public function getStatistics(array $params = []): array
    {
        return $this->mapper->getStatistics($params);
    }

    /**
     * 修改部门状态。
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
     * 修改部门排序。
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
     * 部门写入前统一校验、过滤与唯一性校验。
     */
    protected function filterData(array &$data, array $exists = []): array
    {
        foreach (['code', 'name', 'phone', 'email', 'level', 'leader', 'remark'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        $rules = [
            'tenant_id.integer' => '租户 ID 必须为数字',
            'tenant_id.min:0' => '租户 ID 不能小于 0',
            'pid.integer' => '上级部门必须为数字',
            'pid.min:0' => '上级部门不能小于 0',
            'code.filled' => '部门编码不能为空',
            'code.max:50' => '部门编码最多 50 位',
            'name.filled' => '部门名称不能为空',
            'name.max:30' => '部门名称最多 30 位',
            'phone.max:11' => '联系电话最多 11 位',
            'email.max:50' => '邮箱最多 50 位',
            'level.max:500' => '层级路径最多 500 位',
            'leader.max:20' => '负责人最多 20 位',
            'sort.integer' => '排序值必须为数字',
            'status.integer' => '状态值必须为数字',
            'status.in:1,0' => '状态值错误',
            'remark.max:255' => '备注最多 255 位',
        ];
        if ($exists === []) {
            $rules['code.required'] = '部门编码不能为空';
            $rules['name.required'] = '部门名称不能为空';
            $rules['pid.default'] = 0;
            $rules['sort.default'] = 0;
            $rules['status.default'] = Status::ENABLED;
        }

        $data = _vali($rules, $data);
        foreach (['tenant_id', 'pid', 'sort', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = (int)$data[$field];
            }
        }

        $this->ensureUniqueField('code', $data, $exists, '部门编码已存在');
        $this->ensureUniqueField('name', $data, $exists, '部门名称已存在');

        return $data;
    }
}
