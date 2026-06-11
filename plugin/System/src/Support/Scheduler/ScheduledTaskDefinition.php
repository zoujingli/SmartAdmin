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

use System\Annotation\ScheduledTask;

/**
 * 注解任务定义快照。
 */
final readonly class ScheduledTaskDefinition
{
    public function __construct(
        public string $code,
        public string $name,
        public string $description,
        public string $group,
        public string $ownerPlugin,
        public int $timeout,
        public bool $tenantAware,
        public string $handler,
    ) {}

    public static function fromAnnotation(string $handler, ScheduledTask $annotation): self
    {
        return new self(
            code: $annotation->code,
            name: $annotation->name,
            description: $annotation->description,
            group: $annotation->group,
            ownerPlugin: $annotation->ownerPlugin,
            timeout: max(1, $annotation->timeout),
            tenantAware: $annotation->tenantAware,
            handler: $handler,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'group' => $this->group,
            'owner_plugin' => $this->ownerPlugin,
            'timeout' => $this->timeout,
            'tenant_aware' => $this->tenantAware ? 1 : 0,
            'handler' => $this->handler,
        ];
    }
}
