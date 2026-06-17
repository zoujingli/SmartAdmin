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
 * 定时任务 owner 解析结果。
 */
final readonly class ScheduledTaskOwnerOption
{
    /**
     * @param array<string, mixed> $params 由业务 owner 派生的安全参数，会与后台填写 params 合并。
     */
    public function __construct(
        public string $ownerPlugin,
        public string $ownerType,
        public int $ownerId,
        public string $ownerName,
        public array $params = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'owner_plugin' => $this->ownerPlugin,
            'owner_type' => $this->ownerType,
            'owner_id' => $this->ownerId,
            'owner_name' => $this->ownerName,
            'params' => $this->params,
        ];
    }
}
