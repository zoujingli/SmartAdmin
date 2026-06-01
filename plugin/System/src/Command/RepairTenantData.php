<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace System\Command;

use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Context\Context;
use Hyperf\Di\Annotation\Inject;
use Library\Events\Listener\DbQueryListener;
use Symfony\Component\Console\Input\InputOption;
use System\Service\TenantRepairService;

#[Command(name: 'xadmin:tenant:repair', description: 'Repair default tenant and legacy tenant_id=0 rows')]
final class RepairTenantData extends HyperfCommand
{
    #[Inject]
    protected TenantRepairService $service;

    public function configure(): void
    {
        $this->setDescription('Repair default tenant and legacy tenant_id=0 rows')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview repair without writing to the database')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit the full report as JSON');
    }

    public function handle(): void
    {
        $dryRun = (bool)$this->input->getOption('dry-run');
        $json = (bool)$this->input->getOption('json');
        $report = $json
            ? $this->withoutSqlConsoleNoise(fn (): array => $this->service->repair($dryRun))
            : $this->service->repair($dryRun);

        if ($json) {
            $this->line(json_encode([
                'meta' => [
                    'timestamp' => date('c'),
                    'dry_run' => $dryRun,
                ],
                'report' => $report,
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return;
        }

        $column = $report['system_user_super_column'];
        $uploadConfigTable = $report['upload_config_table'];
        $default = $report['default_tenant'];
        $uniquePrecheck = $report['tenant_unique_precheck'];
        $legacyUploadConfig = $report['legacy_upload_config'];
        $rows = $report['tenant_rows'];
        $platformLogRows = $report['platform_log_rows'];
        $tenantSuper = $report['tenant_super_users'];
        $this->line(sprintf(
            '%s system_user.super column: created=%s, skipped=%s',
            $dryRun ? '[dry-run]' : 'Repair',
            $column['created'] ? 'yes' : 'no',
            $column['skipped'] ? 'yes' : 'no'
        ));
        $this->line(sprintf(
            '%s system_upload_config table: created=%s, skipped=%s',
            $dryRun ? '[dry-run]' : 'Repair',
            $uploadConfigTable['created'] ? 'yes' : 'no',
            $uploadConfigTable['skipped'] ? 'yes' : 'no'
        ));
        $this->line(sprintf(
            '%s default tenant: created=%s, updated=%s, skipped=%s',
            $dryRun ? '[dry-run]' : 'Repair',
            $default['created'] ? 'yes' : 'no',
            $default['updated'] ? 'yes' : 'no',
            $default['skipped'] ? 'yes' : 'no'
        ));
        $this->line(sprintf(
            '%s tenant unique precheck: conflicts=%d',
            $dryRun ? '[dry-run]' : 'Repair',
            (int)$uniquePrecheck['conflicts']
        ));
        foreach ($uniquePrecheck['items'] as $item) {
            $this->line(sprintf(
                '- conflict %s.%s type=%s columns=%s count=%d',
                $item['table'],
                $item['index'],
                $item['type'],
                implode(',', $item['columns']),
                (int)$item['count']
            ));
        }
        $this->line(sprintf(
            '%s legacy upload config: created=%s, updated=%s, skipped=%s',
            $dryRun ? '[dry-run]' : 'Repair',
            $legacyUploadConfig['created'] ? 'yes' : 'no',
            $legacyUploadConfig['updated'] ? 'yes' : 'no',
            $legacyUploadConfig['skipped'] ? 'yes' : 'no'
        ));
        $this->line(sprintf(
            '%s tenant rows: tables=%d, rows=%d',
            $dryRun ? '[dry-run]' : 'Repair',
            (int)$rows['tables'],
            (int)$rows['rows']
        ));
        foreach ($rows['items'] as $item) {
            $extra = [];
            if ((int)($item['kept_id'] ?? 0) > 0) {
                $extra[] = sprintf('kept_id=%d', (int)$item['kept_id']);
            }
            if (($item['deleted_ids'] ?? []) !== []) {
                $extra[] = sprintf('deleted_ids=%s', implode(',', array_map('intval', $item['deleted_ids'])));
            }
            $this->line(sprintf(
                '- %s: %d recent_id=%d recent_created_at=%s recent_updated_at=%s%s',
                $item['table'],
                $item['rows'],
                (int)($item['recent_id'] ?? 0),
                (string)($item['recent_created_at'] ?? '-'),
                (string)($item['recent_updated_at'] ?? '-'),
                $extra === [] ? '' : ' (' . implode(' ', $extra) . ')'
            ));
            foreach (($item['log_summary'] ?? []) as $summary) {
                $this->line(sprintf(
                    '  - log router=%s code=%s username=%s rows=%d recent_id=%d recent_created_at=%s',
                    $summary['router'],
                    $summary['response_code'],
                    $summary['username'],
                    (int)$summary['rows'],
                    (int)$summary['recent_id'],
                    (string)($summary['recent_created_at'] ?? '-')
                ));
            }
        }
        $this->line(sprintf(
            '%s platform log rows: tables=%d, rows=%d',
            $dryRun ? '[dry-run]' : 'Repair',
            (int)$platformLogRows['tables'],
            (int)$platformLogRows['rows']
        ));
        foreach ($platformLogRows['items'] as $item) {
            $this->line(sprintf(
                '- %s: %d recent_id=%d recent_created_at=%s recent_updated_at=%s',
                $item['table'],
                $item['rows'],
                (int)($item['recent_id'] ?? 0),
                (string)($item['recent_created_at'] ?? '-'),
                (string)($item['recent_updated_at'] ?? '-')
            ));
            foreach (($item['log_summary'] ?? []) as $summary) {
                $this->line(sprintf(
                    '  - platform log router=%s code=%s username=%s rows=%d recent_id=%d recent_created_at=%s',
                    $summary['router'],
                    $summary['response_code'],
                    $summary['username'],
                    (int)$summary['rows'],
                    (int)$summary['recent_id'],
                    (string)($summary['recent_created_at'] ?? '-')
                ));
            }
        }
        $this->line(sprintf(
            '%s tenant super users: tenants=%d, users=%d',
            $dryRun ? '[dry-run]' : 'Repair',
            (int)$tenantSuper['tenants'],
            (int)$tenantSuper['users']
        ));
        foreach ($tenantSuper['items'] as $item) {
            $this->line(sprintf('- tenant=%d user=%d username=%s', $item['tenant_id'], $item['user_id'], $item['username']));
        }
        foreach (($tenantSuper['missing_tenant_super_without_candidate'] ?? []) as $tenantId) {
            $this->line(sprintf('- missing tenant super candidate: tenant=%d', (int)$tenantId));
        }
    }

    /**
     * JSON 模式用于脚本解析，必须保证 stdout 只有 JSON；命令执行期间跳过 SQL 查询监听输出。
     *
     * @return array<string, mixed>
     */
    private function withoutSqlConsoleNoise(callable $callback): array
    {
        $existed = Context::has(DbQueryListener::CONTEXT_SUPPRESS_SQL_LOG);
        $previous = Context::get(DbQueryListener::CONTEXT_SUPPRESS_SQL_LOG, false);
        Context::set(DbQueryListener::CONTEXT_SUPPRESS_SQL_LOG, true);

        try {
            return $callback();
        } finally {
            if ($existed) {
                Context::set(DbQueryListener::CONTEXT_SUPPRESS_SQL_LOG, $previous);
            } else {
                Context::destroy(DbQueryListener::CONTEXT_SUPPRESS_SQL_LOG);
            }
        }
    }
}
