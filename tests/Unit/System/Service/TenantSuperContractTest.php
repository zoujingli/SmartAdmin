<?php

declare(strict_types=1);

namespace Tests\Unit\System\Service;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class TenantSuperContractTest extends TestCase
{
    public function testTenantSuperPermissionKeepsPlatformReservedCodesOut(): void
    {
        $source = $this->source('plugin/System/src/Support/TenantSuperPermission.php');
        $access = $this->source('plugin/System/src/Service/UserAccessCodeService.php');
        $menu = $this->source('plugin/System/src/Service/MenuService.php');

        foreach (['system.tenant.', 'system.menu.', 'system.setting.', 'system.data.'] as $prefix) {
            $this->assertStringContainsString("'{$prefix}'", $source);
        }

        $this->assertStringContainsString('$code === \'*\'', $source);
        $this->assertStringContainsString('TenantSuperPermission::codes()', $access);
        $this->assertStringContainsString('$user->getPermissions()', $menu);
        $this->assertStringNotContainsString('system.user.', $source);
    }

    public function testOnlyPlatformSuperAdminCanChangeSuperFlag(): void
    {
        $userService = $this->source('plugin/System/src/Service/UserService.php');
        $boundary = $this->source('plugin/System/src/Service/UserAuthorizationBoundaryService.php');

        $this->assertStringContainsString('assertCanManageTenantSuperStatus', $boundary);
        $this->assertStringContainsString('只有超级管理员可以配置子超管', $boundary);
        $this->assertStringContainsString('user(SystemUser::class)?->isSuper()', $userService);
        $this->assertStringContainsString('unset($data[\'super\'])', $userService);
        $this->assertStringContainsString('assertTenantSuperUserOperationAllowed', $userService);
        $this->assertStringContainsString('只有超级管理员可以%s子超管账号', $boundary);
    }

    public function testPlatformSuperAdminCanManageUsersAcrossTenants(): void
    {
        $mapper = $this->source('plugin/System/src/Mapper/UserMapper.php');
        $service = $this->source('plugin/System/src/Service/UserService.php');

        $this->assertStringContainsString('$scopeUser instanceof SystemUser && $scopeUser->isSuper()', $mapper);
        $this->assertStringContainsString('return;', $mapper);
        $this->assertStringContainsString('user(SystemUser::class)?->isSuper()', $service);
        $this->assertStringContainsString('TenantContext::withExplicitTenantWrite($callback)', $service);
        $this->assertStringNotContainsString('System::isPlatformTenant()', $service);
        $this->assertStringContainsString('applyRequestedTenantScope($query, $params)', $mapper);
    }

    public function testTenantSuperMenusDoNotDependOnManualRoleMenuBinding(): void
    {
        $menu = $this->source('plugin/System/src/Service/MenuService.php');
        $mapper = $this->source('plugin/System/src/Mapper/MenuMapper.php');

        $this->assertStringContainsString('$user->isTenantSuper()', $menu);
        $this->assertStringContainsString('getMenusByPermissionCodes($user->getPermissions())', $menu);
        $this->assertStringContainsString('getEnabledFrontendMenus', $menu);
        $this->assertStringContainsString('getMenusByPermissionCodes', $mapper);
        $this->assertStringContainsString('getEnabledFrontendMenus', $mapper);
        $this->assertStringContainsString('$user->getPermissions()', $menu);
    }

    public function testUserManagementHidesSuperStateFromNonPlatformUsers(): void
    {
        $mapper = $this->source('plugin/System/src/Mapper/UserMapper.php');
        $auth = $this->source('plugin/System/src/Controller/AuthController.php');
        $service = $this->source('plugin/System/src/Service/UserService.php');

        $this->assertStringContainsString('maskTenantSuperAttribute', $mapper);
        $this->assertStringContainsString('$user->makeHidden([\'super\'])', $mapper);
        $this->assertStringContainsString('getUserWithRelations($userId, false, true)', $auth);
        $this->assertStringContainsString('user_with_relations_%d_%d_%d', $service);
    }

    public function testTenantProvisionAndRepairCreateTenantSuperUsers(): void
    {
        $tenant = $this->source('plugin/System/src/Service/TenantService.php');
        $repair = $this->source('plugin/System/src/Service/TenantRepairService.php');

        $this->assertStringContainsString("'super' => 1", $tenant);
        $this->assertStringContainsString('ensureSystemUserSuperColumn', $repair);
        $this->assertStringContainsString('ensureTenantSuperUsers', $repair);
        $this->assertStringContainsString("where('system_role.name', '租户管理员')", $repair);
        $this->assertStringContainsString("->where('id', '!=', System::getSuperId())", $repair);
    }

    public function testFrontendOnlyShowsSuperConfigurationToPlatformSuperAdmin(): void
    {
        $form = $this->source('plugin/System/stc/view/user/modules/form-simple.vue');
        $list = $this->source('plugin/System/stc/view/user/index.vue');

        $this->assertStringContainsString("accessStore.accessCodes.includes('*')", $form);
        $this->assertStringContainsString('v-if="canManageSuper"', $form);
        $this->assertStringContainsString('payload.super', $form);
        $this->assertStringContainsString("accessStore.accessCodes.includes('*')", $list);
        $this->assertStringContainsString('v-if="canManageSuper"', $list);
        $this->assertStringNotContainsString("hasAccessByCodes(['system.tenant.index'])", $form);
    }

    private function source(string $path): string
    {
        $root = dirname(__DIR__, 4);
        $source = (string)file_get_contents($root . '/' . $path);

        $this->assertNotSame('', $source, sprintf('Source file must be readable: %s', $path));

        return $source;
    }
}
