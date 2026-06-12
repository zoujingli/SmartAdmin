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
 * 声明 System 随服务默认注册的后台进程。
 *
 * default=true 表示不依赖环境开关，是否执行具体业务由任务状态和计划配置控制。
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class SystemProcess extends AbstractAnnotation
{
    public function __construct(
        public string $name,
        public bool $default = true,
    ) {}
}
