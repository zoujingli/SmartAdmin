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
use Library\Constants\Status;
use Library\CoreService;
use Library\Exception\ErrorResponseException;
use Library\Helper\ArrayTreeHelper;
use System\Mapper\DictMapper;
use System\Model\SystemDict;

final class DictService extends CoreService
{
    /**
     * @param DictMapper $mapper 字典数据访问层
     */
    public function __construct(
        protected DictMapper $mapper
    ) {}

    /**
     * 创建字典分类或字典项。
     */
    public function create(array $data): ?Model
    {
        $data = $this->normalizeDictPayload($data);
        $this->ensureParentAvailable($data['pid'] ?? 0);

        return parent::create($data);
    }

    /**
     * 更新字典分类或字典项。
     */
    public function update(mixed $id, array $data): bool
    {
        $dict = $this->mapper->read($id);
        if (!$dict instanceof SystemDict) {
            throw new ErrorResponseException('字典不存在');
        }

        $data = $this->normalizeDictPayload($data);
        if (array_key_exists('pid', $data)) {
            if ((int)$data['pid'] === (int)$id) {
                throw new ErrorResponseException('字典不能选择自己作为父级');
            }

            if ((int)$dict->pid === 0 && (int)$data['pid'] > 0 && $this->mapper->hasChildren((int)$id)) {
                throw new ErrorResponseException('存在字典项的分类不能调整为子项');
            }

            $this->ensureParentAvailable((int)$data['pid']);
        }

        return parent::update($id, $data);
    }

    /**
     * 删除字典；分类存在子项时拒绝删除，避免留下孤儿字典项。
     */
    public function delete(array|int $ids): bool
    {
        $this->ensureNoChildren((array)$ids);

        return $this->mapper->delete((array)$ids);
    }

    /**
     * 彻底删除字典；含回收站子项时也拒绝删除。
     */
    public function delreal(array|int $ids): bool
    {
        $this->ensureNoChildren((array)$ids);

        return $this->mapper->delreal((array)$ids);
    }

    /**
     * 获取字典树。
     */
    public function getTree(array $params = []): array
    {
        return ArrayTreeHelper::build($this->mapper->getTree($params));
    }

    /**
     * 获取启用字典项选项。
     *
     * @return array<int, array{label:string,value:string,code:string,name:string,extra:array}>
     */
    public function getOptions(array $params = []): array
    {
        $code = trim((string)($params['code'] ?? ''));
        if ($code === '') {
            throw new ErrorResponseException('字典编码不能为空');
        }

        $category = $this->mapper->findEnabledCategoryByCode($code);
        if (!$category) {
            return [];
        }

        return $this->mapper->getOptionsByCategory($category, $params);
    }

    /**
     * 获取字典统计概览。
     */
    public function getStatistics(array $params = []): array
    {
        return $this->mapper->getStatistics($params);
    }

    /**
     * 修改字典状态。
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
     * 修改字典排序。
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
     * 字典写入前统一校验、过滤和同层唯一性校验。
     */
    protected function filterData(array &$data, array $exists = []): array
    {
        $data = $this->normalizeDictPayload($data);
        foreach (['code', 'name', 'value', 'remark'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        $rules = [
            'pid.integer' => '父级字典必须为数字',
            'pid.min:0' => '父级字典不能小于 0',
            'code.filled' => '字典编码不能为空',
            'code.max:100' => '字典编码最多 100 位',
            'name.filled' => '字典名称不能为空',
            'name.max:100' => '字典名称最多 100 位',
            'value.max:100' => '字典值最多 100 位',
            'sort.integer' => '排序值必须为数字',
            'status.integer' => '状态值必须为数字',
            'status.in:1,0' => '状态值错误',
            'remark.max:255' => '备注最多 255 位',
        ];
        if ($exists === []) {
            $rules['pid.default'] = 0;
            $rules['code.required'] = '字典编码不能为空';
            $rules['name.required'] = '字典名称不能为空';
            $rules['sort.default'] = 0;
            $rules['status.default'] = Status::ENABLED;
        }

        $data = _vali($rules, $data);
        foreach (['pid', 'sort', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = (int)$data[$field];
            }
        }

        $pid = (int)($data['pid'] ?? ($exists['pid'] ?? 0));
        if ($pid > 0 && trim((string)($data['value'] ?? ($exists['value'] ?? ''))) === '') {
            throw new ErrorResponseException('字典项值不能为空');
        }

        $this->ensureSiblingUnique($data, $exists, $pid);

        return $data;
    }

    /**
     * 兼容前端传入的 JSON 字符串扩展配置。
     */
    private function normalizeDictPayload(array $data): array
    {
        if (array_key_exists('extra', $data) && is_string($data['extra'])) {
            $extra = trim($data['extra']);
            $decoded = $extra !== '' ? json_decode($extra, true) : [];
            if ($decoded === null && $extra !== '') {
                throw new ErrorResponseException('扩展配置必须是有效 JSON');
            }
            $data['extra'] = is_array($decoded) ? $decoded : [];
        }

        return $data;
    }

    /**
     * 字典只支持分类和字典项两级结构，父级必须是根分类。
     */
    private function ensureParentAvailable(mixed $pid): void
    {
        $pid = (int)$pid;
        if ($pid <= 0) {
            return;
        }

        $parent = $this->mapper->read($pid);
        if (!$parent instanceof SystemDict) {
            throw new ErrorResponseException('父级字典不存在');
        }

        if ((int)$parent->pid !== 0) {
            throw new ErrorResponseException('字典项不能再添加子项');
        }
    }

    /**
     * 同一父级下编码和值必须保持唯一；分类编码在根级唯一，字典项值在同一分类下唯一。
     */
    private function ensureSiblingUnique(array $data, array $exists, int $pid): void
    {
        $excludeId = (int)($exists['id'] ?? 0);
        if (array_key_exists('code', $data) && $this->mapper->existsSiblingField($pid, 'code', $data['code'], $excludeId)) {
            throw new ErrorResponseException($pid === 0 ? '字典分类编码已存在' : '字典项编码已存在');
        }

        if ($pid > 0 && array_key_exists('value', $data) && $this->mapper->existsSiblingField($pid, 'value', $data['value'], $excludeId)) {
            throw new ErrorResponseException('字典项值已存在');
        }
    }

    /**
     * 删除分类前必须先清理全部子项，包含回收站中的字典项。
     *
     * @param array<int|string, mixed> $ids
     */
    private function ensureNoChildren(array $ids): void
    {
        foreach (array_values(array_unique(array_map('intval', $ids))) as $id) {
            if ($id > 0 && $this->mapper->hasChildren($id)) {
                throw new ErrorResponseException('字典分类下仍存在字典项，无法删除');
            }
        }
    }
}
