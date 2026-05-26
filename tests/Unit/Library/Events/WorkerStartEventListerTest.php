<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Tests\Unit\Library\Events;

use Hyperf\Framework\Event\BeforeWorkerStart;
use Library\Events\Listener\WorkerStartEventLister;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * @internal
 */
#[CoversClass(WorkerStartEventLister::class)]
final class WorkerStartEventListerTest extends TestCase
{
    public function testListenBeforeWorkerStart(): void
    {
        $listener = new WorkerStartEventLister(new NullLogger());

        $this->assertSame([BeforeWorkerStart::class], $listener->listen());
    }

    public function testProcessIgnoresUnrelatedEvent(): void
    {
        $listener = new WorkerStartEventLister(new NullLogger());

        $listener->process(new \stdClass());

        $this->assertTrue(true);
    }
}
