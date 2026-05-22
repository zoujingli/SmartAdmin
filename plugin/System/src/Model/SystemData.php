<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://github.com/zoujingli/SmartAdmin/blob/master/readme.md
 */

namespace System\Model;

use Carbon\Carbon;
use Hyperf\Database\Model\SoftDeletes;
use Library\CoreModel;

/**
 * @property int $id 主键ID
 * @property string $name 配置名称
 * @property string $remark 备注
 * @property int $created_by 创建者
 * @property int $updated_by 更新者
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 * @property string $deleted_at 删除时间
 * @property array $value 配置内容
 */
final class SystemData extends CoreModel
{
    use SoftDeletes;

    protected array $fillable = ['id', 'name', 'value', 'remark', 'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at'];

    protected array $logRules = [
        'name' => '系统配置',
        'title' => 'name',
        'ignore' => ['value'],
        'fields' => [
            'name' => '配置名称',
            'remark' => '备注',
        ],
    ];

    /**
     * 获取数据时转为数组.
     */
    public function getValueAttribute(string $value): array
    {
        return $this->_toArray($value);
    }

    /**
     * 保存数据时转为JSON.
     */
    public function setValueAttribute(mixed $value): void
    {
        $this->_toJson($value, 'value');
    }
}
