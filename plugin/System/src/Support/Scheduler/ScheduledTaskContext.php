<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace System\Support\Scheduler;

/**
 * 单次定时任务执行上下文。
 */
final readonly class ScheduledTaskContext
{
    /**
     * @param array<string, mixed> $params
     */
    public function __construct(
        public int $taskId,
        public int $logId,
        public string $taskCode,
        public int $tenantId,
        public string $ownerPlugin,
        public string $ownerType,
        public int $ownerId,
        public string $ownerName,
        public array $params,
        public bool $manual = false,
    ) {}
}
