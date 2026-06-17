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

use Library\Exception\ErrorResponseException;
use System\Annotation\ScheduledTaskOwner;
use System\Contract\ScheduledTaskOwnerResolverInterface;

#[ScheduledTaskOwner(
    ownerPlugin: 'system',
    ownerType: 'system',
    name: '系统任务',
    description: 'System 内置任务固定归属。'
)]
final class SystemScheduledTaskOwnerResolver implements ScheduledTaskOwnerResolverInterface
{
    public function type(): ScheduledTaskOwnerType
    {
        return new ScheduledTaskOwnerType('system', 'system', '系统任务', 'System 内置任务固定归属。');
    }

    public function options(array $params = []): array
    {
        return [
            'items' => [
                $this->resolve(0)->toArray(),
            ],
            'pageInfo' => [
                'total' => 1,
                'totalPage' => 1,
                'currentPage' => 1,
            ],
        ];
    }

    public function resolve(int $ownerId, array $payload = []): ScheduledTaskOwnerOption
    {
        if ($ownerId !== 0) {
            throw new ErrorResponseException('系统任务归属资源无效');
        }

        return new ScheduledTaskOwnerOption('system', 'system', 0, '系统任务');
    }
}
