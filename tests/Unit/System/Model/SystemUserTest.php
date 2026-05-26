<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Tests\Unit\System\Model;

use Hyperf\Context\Context;
use Library\Constants\Status;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use System\Model\SystemUser;

/**
 * @internal
 */
#[CoversClass(SystemUser::class)]
final class SystemUserTest extends TestCase
{
    public function testDisabledUserHasNoPermission(): void
    {
        $user = new SystemUser();
        $user->id = 2;
        $user->status = Status::DISABLED;

        Context::set('system_user_permissions_set_2', ['system.user.index' => true]);

        $this->assertSame([], $user->getPermissions());
        $this->assertFalse($user->hasPermission('system.user.index'));
    }
}
