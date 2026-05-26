<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Tests\Unit\Library;

use Hyperf\Context\Context;
use Library\Auth\Token;
use Library\Service\LoginService;
use Library\Support\TenantContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Plugin\Project\Model\ProjectAccount;
use System\Model\SystemUser;

/**
 * @internal
 */
#[CoversClass(LoginService::class)]
final class LoginServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        TenantContext::clear();
        Context::destroy('project_account_roles_88');
    }

    public function testMismatchedUserModelDoesNotClearExistingTenantContext(): void
    {
        $token = (new \ReflectionClass(FakeLoginToken::class))->newInstanceWithoutConstructor();
        $service = new LoginService($token);

        TenantContext::set(9);

        // Project 前台请求中通用代码可能误按默认 SystemUser 探测登录态；模型不匹配不能清掉真实租户上下文。
        self::assertNull($service->getUser('project-token', SystemUser::class));
        self::assertSame(9, TenantContext::get());
    }

    public function testApplyTenantContextDoesNotWarmProjectRolesBeforeTenantReady(): void
    {
        $token = (new \ReflectionClass(FakeLoginToken::class))->newInstanceWithoutConstructor();
        $service = new LoginService($token);
        $method = (new \ReflectionClass(LoginService::class))->getMethod('applyTenantContext');
        $method->setAccessible(true);
        $account = new ProjectAccount([
            'id' => 88,
            'tenant_id' => 7,
            'username' => 'tenant-project',
            'nickname' => '租户项目账号',
            'status' => 1,
        ]);

        // 租户上下文建立前不能通过 toArray() 预热 ProjectAccount 角色关系；
        // 否则角色查询会按旧租户上下文缓存为空，导致后续 Auth::CHECK 全部误判无权限。
        $method->invoke($service, $account);

        self::assertSame(7, TenantContext::get());
        self::assertFalse(Context::has('project_account_roles_88'));
    }
}

final class FakeLoginToken extends Token
{
    public function getParserData(?string $token = null): array
    {
        return [
            'uid' => 123,
            'class' => ProjectAccount::class,
        ];
    }
}
