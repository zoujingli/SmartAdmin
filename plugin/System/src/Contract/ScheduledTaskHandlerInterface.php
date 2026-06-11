<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace System\Contract;

use System\Support\Scheduler\ScheduledTaskContext;

interface ScheduledTaskHandlerInterface
{
    /**
     * 执行白名单任务。
     *
     * @return array<string, mixed> 供执行日志保存的摘要数据
     */
    public function handle(ScheduledTaskContext $context): array;
}
