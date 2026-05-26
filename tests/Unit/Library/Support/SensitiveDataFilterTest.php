<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Tests\Unit\Library\Support;

use Library\Support\SensitiveDataFilter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SensitiveDataFilter::class)]
final class SensitiveDataFilterTest extends TestCase
{
    public function testApplyMasksDefaultAndDotPathFields(): void
    {
        $filtered = SensitiveDataFilter::apply([
            'token' => 'token-value',
            'drivers' => [
                'oss' => [
                    'access_id' => 'keep',
                    'access_secret' => 'mask-me',
                ],
            ],
        ], ['drivers.oss.access_secret']);

        $this->assertSame('***', $filtered['token']);
        $this->assertSame('keep', $filtered['drivers']['oss']['access_id']);
        $this->assertSame('***', $filtered['drivers']['oss']['access_secret']);
    }

    public function testApplyTruncatesLongStrings(): void
    {
        $filtered = SensitiveDataFilter::apply(['raw' => 'abcdef'], [], 3);

        $this->assertSame('abc...', $filtered['raw']);
    }
}
