<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Tests\Unit\Library\Constants;

use Library\Constants\DataScope;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DataScope::class)]
final class DataScopeTest extends TestCase
{
    public function testStrictestReturnsNarrowestScope(): void
    {
        $this->assertSame(DataScope::SELF, DataScope::strictest([
            DataScope::ALL,
            DataScope::DEPT,
            DataScope::SELF,
        ]));
    }

    public function testStrictestFallsBackToDefaultForInvalidScopes(): void
    {
        $this->assertSame(DataScope::getDefault(), DataScope::strictest([0, 5, 99]));
    }
}
