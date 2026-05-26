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
use Hyperf\Di\Annotation\Inject;
use Library\Constants\System;
use Symfony\Component\Console\Input\InputOption;
use System\Service\MenuSeedSyncService;

/**
 * 同步模块菜单种子到 `system_menu`。
 *
 * 支持 dry-run、明细输出、JSON 报告与位掩码退出码，便于 CI 集成。
 */
#[Command(name: 'xadmin:menu:sync', description: 'Sync module menu seeds to system_menu')]
final class SyncMenuSeeds extends HyperfCommand
{
    /**
     * 菜单种子同步服务。
     */
    #[Inject]
    protected MenuSeedSyncService $service;

    /**
     * 定义命令参数。
     */
    public function configure(): void
    {
        $this->setDescription('Sync module menu seeds to system_menu')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show the diff without writing to the database')
            ->addOption('details', null, InputOption::VALUE_NONE, 'Print menu details for add and update changes')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit the full report as JSON for CI or scripts')
            ->addOption('exit-code', null, InputOption::VALUE_NONE, 'Return a bitmask exit code: added=1, updated=2');

        if (System::isPharMode()) {
            // 发布包中的菜单同步仅作为极端修复入口保留，正常升级依赖 release 安装包和构建期同步结果。
            $this->setHidden(true);
        }
    }

    /**
     * 执行菜单种子同步流程并输出结果。
     */
    public function handle(): void
    {
        $dryRun = (bool)$this->input->getOption('dry-run');
        $details = (bool)$this->input->getOption('details');
        $json = (bool)$this->input->getOption('json');
        $exitCodeFlag = (bool)$this->input->getOption('exit-code');
        $result = $this->service->syncWithReport($dryRun);

        $exitCode = 0;
        if (($result['added'] ?? 0) > 0) {
            $exitCode |= 1;
        }
        if (($result['updated'] ?? 0) > 0) {
            $exitCode |= 2;
        }

        if ($json) {
            $payload = [
                'meta' => [
                    'timestamp' => date('c'),
                    'dry_run' => $dryRun,
                    'exit_code' => $exitCode,
                    'skipped' => (bool)($result['skipped'] ?? false),
                ],
                'summary' => [
                    'added' => (int)($result['added'] ?? 0),
                    'updated' => (int)($result['updated'] ?? 0),
                    'touched' => (int)($result['touched'] ?? 0),
                ],
                'details' => [
                    'added_menus' => $details ? (array)($result['added_menus'] ?? []) : [],
                    'updated_menus' => $details ? (array)($result['updated_menus'] ?? []) : [],
                    'details_included' => $details,
                ],
            ];
            $this->line(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            if ($exitCodeFlag) {
                exit($exitCode);
            }
            return;
        }

        $prefix = ($result['skipped'] ?? false)
            ? 'Sync skipped: system_menu table does not exist'
            : ($dryRun ? 'Dry run complete' : 'Sync complete');
        $this->line(sprintf(
            '%s: added %d, updated %d, touched %d',
            $prefix,
            $result['added'],
            $result['updated'],
            $result['touched']
        ));

        if ($details) {
            $this->line('--- Added Menus ---');
            foreach ($result['added_menus'] as $menu) {
                $this->line((string)$menu);
            }
            $this->line('--- Updated Menus ---');
            foreach ($result['updated_menus'] as $menu) {
                $this->line((string)$menu);
            }
        }

        if ($exitCodeFlag) {
            exit($exitCode);
        }
    }
}
