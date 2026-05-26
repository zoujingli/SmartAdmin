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
 * @property int $action_id 操作日志ID
 * @property string $username 操作用户名
 * @property string $model 模型短名
 * @property string $table_name 业务表名
 * @property string $model_name 业务对象名称
 * @property string $record_id 业务记录ID
 * @property string $record_label 业务记录展示名
 * @property string $event 变更动作(created,updated,deleted,force_deleted,restored)
 * @property string $change_values 字段变化明细(JSON)
 * @property string $change_remark 可读变更描述
 * @property int $created_by 创建者
 * @property int $updated_by 更新者
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 * @property string $deleted_at 删除时间
 */
final class SystemLogsChange extends CoreModel
{
    use SoftDeletes;

    protected ?string $table = 'system_logs_change';

    /**
     * 变更日志按业务对象拆分，一条记录只描述一个对象在一次操作中的字段变化。
     */
    protected array $fillable = ['id', 'tenant_id', 'action_id', 'username', 'model', 'table_name', 'model_name', 'record_id', 'record_label', 'event', 'change_values', 'change_remark', 'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at'];
}
