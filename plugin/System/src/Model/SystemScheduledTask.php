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
 * @property int $tenant_id 租户ID
 * @property string $owner_plugin 归属插件
 * @property string $owner_type 归属类型
 * @property int $owner_id 归属资源ID
 * @property string $owner_name 归属资源名称
 * @property string $code 任务编码
 * @property string $name 任务名称
 * @property string $group_name 任务分组
 * @property string $schedule_type 周期类型
 * @property int $timeout 超时时间秒
 * @property string $next_run_at 下次执行时间
 * @property string $last_run_at 最后执行时间
 * @property string $last_status 最后执行状态
 * @property string $last_message 最后执行消息
 * @property int $running 是否执行中
 * @property string $locked_until 锁过期时间
 * @property int $status 状态(1启用,0禁用)
 * @property string $remark 备注
 * @property int $created_by 创建者
 * @property int $updated_by 更新者
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 * @property string $deleted_at 删除时间
 * @property string $lock_token 执行锁令牌
 * @property array|mixed $schedule_config 周期配置JSON
 * @property array|mixed $params 执行参数JSON
 */
final class SystemScheduledTask extends CoreModel
{
    use SoftDeletes;

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_RUNNING = 'running';

    public const STATUS_PENDING = 'pending';

    protected ?string $table = 'system_scheduled_task';

    protected array $fillable = ['id', 'tenant_id', 'owner_plugin', 'owner_type', 'owner_id', 'owner_name', 'code', 'name', 'group_name', 'schedule_type', 'schedule_config', 'params', 'timeout', 'next_run_at', 'last_run_at', 'last_status', 'last_message', 'running', 'locked_until', 'status', 'remark', 'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at', 'lock_token'];

    protected array $logRules = [
        'name' => '定时任务',
        'title' => 'name',
        'fields' => [
            'name' => '任务名称',
            'owner_plugin' => '归属插件',
            'owner_type' => '归属类型',
            'owner_id' => '归属资源ID',
            'owner_name' => '归属资源',
            'code' => '任务编码',
            'schedule_type' => '周期类型',
            'schedule_config' => '周期配置',
            'params' => '执行参数',
            'next_run_at' => '下次执行时间',
            'status' => ['name' => '状态', 'values' => [Status::DISABLED => '禁用', Status::ENABLED => '启用']],
            'remark' => '备注',
        ],
    ];

    public function setScheduleConfigAttribute(mixed $value): string
    {
        return $this->_toJson($value, 'schedule_config');
    }

    public function getScheduleConfigAttribute(mixed $value): array
    {
        return $this->_toArray($value);
    }

    public function setParamsAttribute(mixed $value): string
    {
        return $this->_toJson($value, 'params');
    }

    public function getParamsAttribute(mixed $value): array
    {
        return $this->_toArray($value);
    }
}
