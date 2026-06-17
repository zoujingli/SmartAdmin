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

use System\Support\Scheduler\ScheduledTaskOwnerOption;
use System\Support\Scheduler\ScheduledTaskOwnerType;

interface ScheduledTaskOwnerResolverInterface
{
    public function type(): ScheduledTaskOwnerType;

    /**
     * @param array<string, mixed> $params
     * @return array{items:list<array<string,mixed>>,pageInfo:array<string,int>}
     */
    public function options(array $params = []): array;

    /**
     * @param array<string, mixed> $payload
     */
    public function resolve(int $ownerId, array $payload = []): ScheduledTaskOwnerOption;
}
