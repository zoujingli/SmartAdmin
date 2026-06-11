<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace System\Support\Scheduler\Task;

use Hyperf\DbConnection\Db;
use System\Annotation\ScheduledTask;
use System\Contract\ScheduledTaskHandlerInterface;
use System\Support\Scheduler\ScheduledTaskContext;

#[ScheduledTask(
    code: 'system.logs.clear',
    name: '清理操作日志',
    description: '按保留天数清理 system_logs_action 及关联变更日志。',
    group: 'system',
    timeout: 1800
)]
final class SystemLogsClearTask implements ScheduledTaskHandlerInterface
{
    public function handle(ScheduledTaskContext $context): array
    {
        $days = max(1, min(3650, (int)($context->params['days'] ?? 180)));
        $date = date('Y-m-d H:i:s', strtotime("-{$days} days") ?: time());
        $deleted = Db::transaction(function () use ($context, $date): int {
            $actionIds = Db::table('system_logs_action')
                ->where('tenant_id', $context->tenantId)
                ->where('created_at', '<', $date)
                ->pluck('id')
                ->toArray();
            if ($actionIds === []) {
                return 0;
            }

            Db::table('system_logs_change')->whereIn('action_id', $actionIds)->update(['deleted_at' => date('Y-m-d H:i:s')]);

            return Db::table('system_logs_action')
                ->whereIn('id', $actionIds)
                ->update(['deleted_at' => date('Y-m-d H:i:s')]);
        });

        return [
            'days' => $days,
            'before' => $date,
            'deleted' => $deleted,
        ];
    }
}
