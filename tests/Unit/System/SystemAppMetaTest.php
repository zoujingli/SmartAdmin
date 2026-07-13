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
        self::assertSame([], SystemAppMeta::defaults()['module_guide_visibility']);
        self::assertTrue(SystemAppMeta::mergeDefaults([])['module_guide_enable']);
        self::assertSame([], SystemAppMeta::mergeDefaults([])['module_guide_visibility']);
    }

    public function testModuleGuideCanBeDisabledFromCurrentMetaOrLegacyConfig(): void
    {
        self::assertFalse(SystemAppMeta::mergeDefaults(['module_guide_enable' => false])['module_guide_enable']);
        self::assertFalse(SystemAppMeta::mergeDefaults([], ['module_guide_enable' => false])['module_guide_enable']);
    }

    public function testModuleGuideVisibilityIsPreservedInCurrentMeta(): void
    {
        self::assertSame(
            ['demo' => false, 'system' => true],
            SystemAppMeta::mergeDefaults([
                'module_guide_visibility' => ['demo' => false, 'system' => true],
            ])['module_guide_visibility']
        );
    }
}
