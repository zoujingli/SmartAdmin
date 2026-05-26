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

use Library\Events\Annotation\Auth;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use System\Model\SystemUser;

/**
 * @internal
 */
#[CoversClass(Auth::class)]
final class AuthAnnotationTest extends TestCase
{
    public function testUserModelSelectsExplicitLoginModel(): void
    {
        $auth = new Auth(userModel: FakeAuthAnnotationUser::class);

        $this->assertSame(FakeAuthAnnotationUser::class, $auth->userModel);
        $this->assertArrayNotHasKey('scene', $auth->toArray());
    }

    public function testDefaultUserModelIsSystemUser(): void
    {
        $auth = new Auth();

        $this->assertSame(SystemUser::class, $auth->userModel);
        $this->assertArrayNotHasKey('scene', $auth->toArray());
    }
}

final class FakeAuthAnnotationUser {}
