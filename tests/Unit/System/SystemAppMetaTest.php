<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Tests\Unit\System;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use System\Support\SystemAppMeta;

/**
 * @internal
 */
#[CoversClass(SystemAppMeta::class)]
final class SystemAppMetaTest extends TestCase
{
    public function testModuleGuideIsEnabledByDefault(): void
    {
        self::assertTrue(SystemAppMeta::defaults()['module_guide_enable']);
        self::assertTrue(SystemAppMeta::mergeDefaults([])['module_guide_enable']);
    }

    public function testModuleGuideCanBeDisabledFromCurrentMetaOrLegacyConfig(): void
    {
        self::assertFalse(SystemAppMeta::mergeDefaults(['module_guide_enable' => false])['module_guide_enable']);
        self::assertFalse(SystemAppMeta::mergeDefaults([], ['module_guide_enable' => false])['module_guide_enable']);
    }
}
