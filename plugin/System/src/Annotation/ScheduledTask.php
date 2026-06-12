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
 * 声明可被 System 定时任务后台选择的白名单任务。
 *
 * 注解只暴露任务元信息，不接受后台输入任意命令；具体执行逻辑必须由实现类控制参数、租户和异常边界。
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class ScheduledTask extends AbstractAnnotation
{
    public function __construct(
        public string $code,
        public string $name,
        public string $description = '',
        public string $group = 'system',
        public string $ownerPlugin = 'system',
        public int $timeout = 3600,
        public bool $tenantAware = true,
    ) {}
}
