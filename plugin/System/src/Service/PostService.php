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

use Library\Constants\Status;
use Library\CoreService;
use System\Mapper\PostMapper;

final class PostService extends CoreService
{
    /**
     * @param PostMapper $mapper 岗位数据访问层
     */
    public function __construct(
        protected PostMapper $mapper
    ) {}

    /**
     * 获取岗位选项.
     */
    public function getOptions(array $params = []): array
    {
        return $this->mapper->getNormalOptions($params);
    }

    /**
     * 获取岗位统计概览.
     */
    public function getStatistics(array $params = []): array
    {
        return $this->mapper->getStatistics($params);
    }

    /**
     * 修改岗位状态。
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
     * 岗位写入前统一校验、过滤与唯一性校验。
     */
    protected function filterData(array &$data, array $exists = []): array
    {
        foreach (['code', 'name', 'remark'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        $rules = [
            'tenant_id.integer' => '租户 ID 必须为数字',
            'tenant_id.min:0' => '租户 ID 不能小于 0',
            'code.filled' => '岗位编码不能为空',
            'code.max:100' => '岗位编码最多 100 位',
            'name.filled' => '岗位名称不能为空',
            'name.max:100' => '岗位名称最多 100 位',
            'sort.integer' => '排序值必须为数字',
            'status.integer' => '状态值必须为数字',
            'status.in:1,0' => '状态值错误',
            'remark.max:255' => '备注最多 255 位',
        ];
        if ($exists === []) {
            $rules['code.required'] = '岗位编码不能为空';
            $rules['name.required'] = '岗位名称不能为空';
            $rules['sort.default'] = 0;
            $rules['status.default'] = Status::ENABLED;
        }

        $data = _vali($rules, $data);
        foreach (['tenant_id', 'sort', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = (int)$data[$field];
            }
        }

        $this->ensureUniqueField('code', $data, $exists, '岗位编码已存在');
        $this->ensureUniqueField('name', $data, $exists, '岗位名称已存在');

        return $data;
    }
}
