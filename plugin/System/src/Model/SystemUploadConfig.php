<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace System\Model;

use Carbon\Carbon;
use Hyperf\Database\Model\SoftDeletes;
use Library\CoreModel;

/**
 * @property int $id 主键ID
 * @property int $tenant_id 租户ID
 * @property array $value 上传通道配置
 * @property string $remark 备注
 * @property int $created_by 创建者
 * @property int $updated_by 更新者
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 * @property string $deleted_at 删除时间
 */
final class SystemUploadConfig extends CoreModel
{
    use SoftDeletes;

    protected ?string $table = 'system_upload_config';

    protected array $fillable = ['id', 'tenant_id', 'value', 'remark', 'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at'];

    protected array $casts = ['id' => 'integer', 'tenant_id' => 'integer', 'created_by' => 'integer', 'updated_by' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];

    protected array $logRules = [
        'name' => '上传通道配置',
        'title' => 'tenant_id',
        'ignore' => ['value'],
        'fields' => [
            'tenant_id' => '租户',
            'remark' => '备注',
        ],
    ];

    /**
     * 获取数据时转为数组。
     */
    public function getValueAttribute(string $value): array
    {
        return $this->_toArray($value);
    }

    /**
     * 保存数据时转为 JSON。
     */
    public function setValueAttribute(mixed $value): void
    {
        $this->_toJson($value, 'value');
    }
}
