<?php

declare(strict_types=1);

namespace Tests\Unit\Library\Constants;

use Library\Constants\DataScope;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

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
