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
 * 定时任务 owner 类型元信息。
 */
final readonly class ScheduledTaskOwnerType
{
    public function __construct(
        public string $ownerPlugin,
        public string $ownerType,
        public string $name,
        public string $description = '',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'owner_plugin' => $this->ownerPlugin,
            'owner_type' => $this->ownerType,
            'name' => $this->name,
            'description' => $this->description,
        ];
    }
}
