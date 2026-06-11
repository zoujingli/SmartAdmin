<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace System\Service;

use Library\CoreService;
use System\Mapper\ScheduledTaskLogMapper;

final class ScheduledTaskLogService extends CoreService
{
    public function __construct(
        protected ScheduledTaskLogMapper $mapper,
    ) {}
}
