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

use System\Model\SystemScheduledTask;

interface ScheduledTaskExecutorInterface
{
    /**
     * 执行已抢占的任务实例并写入日志。
     */
    public function execute(SystemScheduledTask $task, bool $manual = false): array;
}
