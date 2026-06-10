<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Tests\Unit\System\Service;

use Library\Exception\ErrorResponseException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use System\Mapper\UserMapper;
use System\Service\UserPasswordCredentialService;

/**
 * @internal
 */
#[CoversClass(UserPasswordCredentialService::class)]
final class UserPasswordCredentialServiceTest extends TestCase
{
    public function testCreatePasswordAllowsFiveCharacters(): void
    {
        $service = $this->makeService();

        // System 后台账号最短密码为 5 位，新增用户入口必须接受边界值。
        $service->assertCreatePassword(['password' => '12345']);

        $this->addToAssertionCount(1);
    }

    public function testCreatePasswordRejectsFourCharacters(): void
    {
        $service = $this->makeService();

        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('密码长度至少 5 位');

        $service->assertCreatePassword(['password' => '1234']);
    }

    public function testUpdatePasswordAllowsFiveCharacters(): void
    {
        $service = $this->makeService();

        // 编辑用户时非空密码同样执行 5 位边界；空密码仍由原逻辑表示不修改。
        $payload = $service->normalizeUpdatePassword(['password' => '12345']);

        self::assertSame(['password' => '12345'], $payload);
    }

    public function testUpdatePasswordRejectsFourCharacters(): void
    {
        $service = $this->makeService();

        $this->expectException(ErrorResponseException::class);
        $this->expectExceptionMessage('密码长度至少 5 位');

        $service->normalizeUpdatePassword(['password' => '1234']);
    }

    public function testSystemPasswordSourcesDoNotKeepOldSixCharacterMinimum(): void
    {
        $root = dirname(__DIR__, 4);
        $files = [
            'plugin/System/src/Service/UserPasswordCredentialService.php',
            'plugin/System/src/Service/TenantService.php',
            'plugin/System/src/Command/ResetAdminPassword.php',
            'plugin/System/stc/view/user/data.ts',
            'plugin/System/stc/view/user/index.vue',
            'plugin/System/stc/view/tenant/modules/form.vue',
            'plugin/System/stc/view/tenant/index.vue',
            'plugin/System/stc/languages/zh_CN/system.php',
            'plugin/System/stc/languages/zh_TW/system.php',
            'plugin/System/stc/languages/en_US/system.php',
        ];
        $patterns = [
            '密码长度至少 6 位',
            '新密码长度至少 6 位',
            '至少6位',
            'Password must be at least 6',
            'New password must be at least 6',
            'Password length must be at least 6',
            'admin_password.min:6',
            "min(6, '密码至少 6 位')",
            'password.length < 6',
        ];

        foreach ($files as $file) {
            $source = (string)file_get_contents($root . '/' . $file);
            foreach ($patterns as $pattern) {
                self::assertStringNotContainsString($pattern, $source, sprintf('%s should not contain old password rule: %s', $file, $pattern));
            }
        }
    }

    private function makeService(): UserPasswordCredentialService
    {
        return new UserPasswordCredentialService(new UserMapper());
    }
}
