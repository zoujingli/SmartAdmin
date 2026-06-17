<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace System\Annotation;

use Hyperf\Di\Annotation\AbstractAnnotation;

/**
 * 声明定时任务 owner 解析器。
 *
 * System 全局任务管理只能通过解析器选择业务资源，避免后台裸填 owner_id 后生成无效或越权的业务计划。
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class ScheduledTaskOwner extends AbstractAnnotation
{
    public function __construct(
        public string $ownerPlugin,
        public string $ownerType,
        public string $name,
        public string $description = '',
    ) {}
}
