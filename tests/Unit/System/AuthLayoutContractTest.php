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

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNothing]
final class AuthLayoutContractTest extends TestCase
{
    public function testAuthenticationLogoNavigatesToRootHome(): void
    {
        $source = (string)file_get_contents(dirname(__DIR__, 3) . '/web/apps/web-antd/src/layouts/auth.vue');

        self::assertStringContainsString("import { useRouter } from 'vue-router';", $source);
        self::assertStringContainsString('const router = useRouter();', $source);
        self::assertStringContainsString('function goHome()', $source);
        self::assertStringContainsString("void router.push('/');", $source);
        self::assertStringContainsString(':click-logo="goHome"', $source);
    }
}
