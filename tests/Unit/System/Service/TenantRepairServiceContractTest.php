<?php

declare(strict_types=1);

namespace Tests\Unit\System\Service;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class TenantRepairServiceContractTest extends TestCase
{
    public function testDefaultTenantRepairContractIsInstalledInBootstrapAndCommand(): void
    {
        $repair = $this->source('plugin/System/src/Service/TenantRepairService.php');
        $bootstrap = $this->source('plugin/System/src/Service/SystemBootstrapService.php');
        $command = $this->source('plugin/System/src/Command/RepairTenantData.php');

        $this->assertStringContainsString('TenantContext::DEFAULT_TENANT_ID', $repair);
        $this->assertStringContainsString('ensureUploadConfigTable', $repair);
        $this->assertStringContainsString('ensureDefaultTenant', $repair);
        $this->assertStringContainsString('precheckZeroTenantUniqueConflicts', $repair);
        $this->assertStringContainsString('detectUniqueIndexConflicts', $repair);
        $this->assertStringContainsString('tenant_unique_precheck', $repair);
        $this->assertStringContainsString('历史租户数据修复存在唯一键冲突', $repair);
        $this->assertStringContainsString('repairZeroTenantRows', $repair);
        $this->assertStringContainsString('tenantDirtyRowDiagnostics', $repair);
        $this->assertStringContainsString("'recent_id' =>", $repair);
        $this->assertStringContainsString("'recent_created_at' =>", $repair);
        $this->assertStringContainsString("'recent_updated_at' =>", $repair);
        $this->assertStringContainsString('PLATFORM_LOG_TABLE', $repair);
        $this->assertStringContainsString('TENANT_DELETE_IGNORED_AUDIT_TABLES', $repair);
        $this->assertStringContainsString('platformLogRows', $repair);
        $this->assertStringContainsString('businessTenantTables', $repair);
        $this->assertStringContainsString('tenantDeleteBusinessTables', $repair);
        $this->assertStringContainsString('$diagnostics[\'log_summary\']', $repair);
        $this->assertStringContainsString('$diagnostics = $this->tenantDirtyRowDiagnostics($table);', $repair);
        $this->assertStringContainsString("->groupBy('router', 'response_code', 'username')", $repair);
        $this->assertStringContainsString('migrateLegacyUploadConfigToDefaultTenant', $repair);
        $this->assertStringContainsString('repairUploadConfigZeroTenantRows', $repair);
        $this->assertStringContainsString('system_upload_config', $repair);
        $this->assertStringContainsString('whereIn(\'id\', $deleteIds)->delete()', $repair);
        $this->assertStringContainsString("'kept_id' => \$keepId", $repair);
        $this->assertStringContainsString("'deleted_ids' => \$deleteIds", $repair);
        $this->assertStringContainsString("kept_id=%d", $command);
        $this->assertStringContainsString("deleted_ids=%s", $command);
        $this->assertStringContainsString('recent_id=%d recent_created_at=%s recent_updated_at=%s', $command);
        $this->assertStringContainsString('log router=%s code=%s username=%s rows=%d recent_id=%d recent_created_at=%s', $command);
        $this->assertStringContainsString('platform log rows', $command);
        $this->assertStringContainsString('platform log router=%s code=%s username=%s rows=%d recent_id=%d recent_created_at=%s', $command);
        $this->assertStringContainsString("where('name', 'config_upload')", $repair);
        $this->assertStringContainsString('默认租户已有独立配置后，修复命令不得再次覆盖租户自行保存的配置', $repair);
        $this->assertStringContainsString("whereNull('tenant_id')->orWhere('tenant_id', '<=', TenantContext::UNSET_TENANT_ID)", $repair);
        $this->assertStringContainsString('getDoctrineSchemaManager()->listTableNames()', $repair);
        $this->assertStringContainsString('$table !== self::PLATFORM_LOG_TABLE', $repair);
        $this->assertStringContainsString("'system_logs_change'", $repair);
        $this->assertMatchesRegularExpression('/tenant_rows[\\s\\S]+legacy_upload_config/', $repair);
        $this->assertStringContainsString('TenantRepairService())->repair($dryRun)', $bootstrap);
        $this->assertStringContainsString("xadmin:tenant:repair", $command);
        $this->assertStringContainsString("addOption('dry-run'", $command);
        $this->assertStringContainsString('withoutSqlConsoleNoise', $command);
        $this->assertStringContainsString('DbQueryListener::CONTEXT_SUPPRESS_SQL_LOG', $command);
        $this->assertStringContainsString('tenant unique precheck', $command);
        $this->assertStringContainsString('legacy upload config', $command);
    }

    public function testDefaultTenantSeedAndProtectionUseFixedTenantOne(): void
    {
        $context = $this->source('plugin/Library/Support/TenantContext.php');
        $seed = $this->source('plugin/System/src/Support/SystemBootstrapSeed.php');
        $tenant = $this->source('plugin/System/src/Service/TenantService.php');
        $form = $this->source('plugin/System/stc/view/tenant/modules/form.vue');
        $list = $this->source('plugin/System/stc/view/tenant/index.vue');

        $this->assertStringContainsString('DEFAULT_TENANT_ID = 1', $context);
        $this->assertStringContainsString('TenantContext::DEFAULT_TENANT_ID', $seed);
        $this->assertStringContainsString('assertDefaultTenantMutable', $tenant);
        $this->assertStringContainsString('assertTenantsHaveNoBusinessData', $tenant);
        $this->assertStringContainsString('tenantDeleteBusinessTables()', $tenant);
        $this->assertStringContainsString('租户存在业务数据，禁止彻底删除', $tenant);
        $this->assertStringContainsString("->where('tenant_id', \$tenantId)->count()", $tenant);
        $this->assertStringNotContainsString("->where('tenant_id', \$tenantId)->limit(1)->count()", $tenant);
        $this->assertStringContainsString('默认租户不允许', $tenant);
        $this->assertStringContainsString("array_intersect_key(\$data, array_flip(['contact_name', 'contact_phone', 'contact_email', 'remark']))", $tenant);
        $this->assertStringContainsString('isDefaultTenant', $form);
        $this->assertStringContainsString('selectedNonDefaultRowKeys', $list);
        $this->assertStringContainsString('selectedNonDefaultRecycleRowKeys', $list);
    }

    public function testTenantSuperRepairReportsMissingCandidateInsteadOfCreatingDefaultAccount(): void
    {
        $repair = $this->source('plugin/System/src/Service/TenantRepairService.php');
        $command = $this->source('plugin/System/src/Command/RepairTenantData.php');

        $this->assertStringContainsString('missing_tenant_super_without_candidate', $repair);
        $this->assertStringContainsString('$missing[] = $tenantId', $repair);
        $this->assertStringContainsString('missing tenant super candidate', $command);
        $this->assertStringNotContainsString('admin_password', $repair);
    }

    private function source(string $path): string
    {
        $root = dirname(__DIR__, 4);
        $candidates = [$root . '/' . $path];

        if (str_starts_with($path, 'plugin/Library/')) {
            // Developer 仓使用 plugin/Library，公开 SmartAdmin 仓通过 Composer 安装到 vendor。
            $candidates[] = $root . '/vendor/zoujingli/smart-admin-library/' . substr($path, strlen('plugin/Library/'));
        }

        foreach ($candidates as $candidate) {
            if (!is_file($candidate)) {
                continue;
            }

            $source = (string)file_get_contents($candidate);
            if ($source !== '') {
                return $source;
            }
        }

        $this->fail(sprintf('Source file must be readable: %s', $path));
    }
}
