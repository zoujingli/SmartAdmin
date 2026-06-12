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
use Library\CoreModel;

/**
 * @property int $id 主键ID
 * @property int $tenant_id 租户ID
 * @property int $task_id 任务ID
 * @property string $owner_plugin 归属插件
 * @property string $owner_type 归属类型
 * @property int $owner_id 归属资源ID
 * @property string $owner_name 归属资源名称
 * @property string $task_code 任务编码
 * @property string $task_name 任务名称
 * @property string $trigger_type 触发方式
 * @property string $status 状态
 * @property string $message 结果消息
 * @property string $started_at 开始时间
 * @property string $finished_at 结束时间
 * @property int $duration_ms 耗时毫秒
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 * @property array|mixed $result 结果摘要JSON
 */
final class SystemScheduledTaskLog extends CoreModel
{
    public const TRIGGER_AUTO = 'auto';

    public const TRIGGER_MANUAL = 'manual';

    protected ?string $table = 'system_scheduled_task_log';

    protected array $fillable = ['id', 'tenant_id', 'task_id', 'owner_plugin', 'owner_type', 'owner_id', 'owner_name', 'task_code', 'task_name', 'trigger_type', 'status', 'message', 'result', 'started_at', 'finished_at', 'duration_ms', 'created_at', 'updated_at'];

    public function setResultAttribute(mixed $value): string
    {
        return $this->_toJson($value, 'result');
    }

    public function getResultAttribute(mixed $value): array
    {
        return $this->_toArray($value);
    }
}
