<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://github.com/zoujingli/SmartAdmin/blob/master/readme.md
 */

namespace System\Support;

use Library\Support\ModuleRegistry;

/**
 * @deprecated use {@see ModuleRegistry}; kept for existing callers
 */
final class SystemModuleRegistry
{
    public static function commonCapabilities(): array
    {
        return ModuleRegistry::commonCapabilities();
    }

    public static function modules(): array
    {
        return ModuleRegistry::modules();
    }
}
