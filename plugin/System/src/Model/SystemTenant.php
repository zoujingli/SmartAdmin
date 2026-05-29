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
use Library\Constants\Status;
use Library\CoreModel;

/**
 * @property int $id 主键ID
 * @property string $code 租户编码
 * @property string $name 租户名称
 * @property string $contact_name 联系人姓名
 * @property string $contact_phone 联系电话
 * @property string $contact_email 联系邮箱
 * @property string $package_code 套餐编码
 * @property string $expired_at 到期时间
 * @property int $status 状态(1启用,0禁用)
 * @property string $remark 备注
 * @property int $created_by 创建者
 * @property int $updated_by 更新者
 * @property string $deleted_at 删除时间
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 * @property string $status_text
 */
final class SystemTenant extends CoreModel
{
    use SoftDeletes;

    protected ?string $table = 'system_tenant';

    /**
     * 可批量赋值的属性。
     */
    protected array $fillable = ['id', 'code', 'name', 'contact_name', 'contact_phone', 'contact_email', 'package_code', 'expired_at', 'status', 'remark', 'created_by', 'updated_by', 'deleted_at', 'created_at', 'updated_at'];

    protected array $logRules = [
        'name' => '租户',
        'title' => 'name',
        'fields' => [
            'code' => '租户编码',
            'name' => '租户名称',
            'contact_name' => '联系人',
            'contact_phone' => '联系电话',
            'contact_email' => '联系邮箱',
            'package_code' => '套餐编码',
            'expired_at' => '到期时间',
            'status' => ['name' => '状态', 'values' => [Status::DISABLED => '禁用', Status::ENABLED => '启用']],
            'remark' => '备注',
        ],
    ];

    public function getStatusTextAttribute(): string
    {
        return Status::getText((int)$this->status);
    }
}
