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
use System\Service\AuthRegistryService;
use System\Service\MenuSeedSyncService;

/**
 * 同步鉴权节点到 `system_node` 注册表。
 *
 * 支持注解(@Auth)与菜单 code 双来源同步，可输出 JSON 报告供 CI 消费。
 */
#[Command(name: 'xadmin:node:sync', description: 'Sync auth nodes to system_node (sources: @Auth and menu code)')]
final class SyncAuthNodes extends HyperfCommand
{
    /**
     * 菜单种子同步服务（节点同步前用于保证菜单基线已就绪）。
     */
    #[Inject]
    protected MenuSeedSyncService $menuSeeds;

    /**
     * 鉴权节点注册表同步服务（负责差异计算与落库）。
     */
    #[Inject]
    protected AuthRegistryService $authRegistry;

    /**
     * 定义命令参数。
     *
     * - only: 仅同步 annotation/menu 单一来源
     * - dry-run: 仅输出差异，不写库
     * - skip-menu-sync: 跳过菜单种子预同步
     * - details/json/exit-code: 控制输出明细与脚本退出码
     */
    public function configure(): void
    {
        $this->setDescription('Sync auth nodes to system_node (sources: @Auth and menu code)')
            ->addOption('only', null, InputOption::VALUE_OPTIONAL, 'Only sync one source: annotation or menu', '')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show the diff without writing to the database')
            ->addOption('skip-menu-sync', null, InputOption::VALUE_NONE, 'Skip syncing module menu seeds before node sync')
            ->addOption('details', null, InputOption::VALUE_NONE, 'Print node details for add, update, and disable changes')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit the full report as JSON for CI or scripts')
            ->addOption('exit-code', null, InputOption::VALUE_NONE, 'Return a bitmask exit code: added=1, updated=2, disabled=4');

        if (System::isPharMode()) {
            // 发布包中的节点同步仅用于权限注册表应急修复，避免生产 list 继续提示常规发布后执行。
            $this->setHidden(true);
        }
    }

    /**
     * 执行节点同步主流程并输出文本/JSON 结果。
     *
     * 启用 `--exit-code` 时返回位掩码：added=1、updated=2、disabled=4。
     */
    public function handle(): void
    {
        $only = (string)$this->input->getOption('only');
        $dryRun = (bool)$this->input->getOption('dry-run');
        $skipMenuSeedSync = (bool)$this->input->getOption('skip-menu-sync');
        $details = (bool)$this->input->getOption('details');
        $json = (bool)$this->input->getOption('json');
        $exitCodeFlag = (bool)$this->input->getOption('exit-code');
        $syncAnnotation = true;
        $syncMenu = true;

        if ($only !== '') {
            if (!in_array($only, ['annotation', 'menu'], true)) {
                $this->error('Invalid --only value. Allowed values: annotation, menu.');
                exit(1);
            }

            $syncAnnotation = $only === 'annotation';
            $syncMenu = $only === 'menu';
        }

        $menuSeedResult = (!$skipMenuSeedSync && $syncMenu) ? $this->menuSeeds->syncWithReport($dryRun) : null;
        $result = $this->authRegistry->syncWithReport($syncAnnotation, $syncMenu, $dryRun);

        $exitCode = 0;
        if (($result['added'] ?? 0) > 0) {
            $exitCode |= 1;
        }
        if (($result['updated'] ?? 0) > 0) {
            $exitCode |= 2;
        }
        if (($result['disabled'] ?? 0) > 0) {
            $exitCode |= 4;
        }

        if ($json) {
            $payload = [
                'meta' => [
                    'timestamp' => date('c'),
                    'dry_run' => $dryRun,
                    'only' => $only,
                    'sources' => [
                        'annotation' => $syncAnnotation,
                        'menu' => $syncMenu,
                    ],
                    'exit_code' => $exitCode,
                    'menu_seed_sync' => $menuSeedResult !== null,
                ],
                'menu_seed_summary' => $menuSeedResult === null ? null : [
                    'added' => (int)($menuSeedResult['added'] ?? 0),
                    'updated' => (int)($menuSeedResult['updated'] ?? 0),
                    'touched' => (int)($menuSeedResult['touched'] ?? 0),
                    'skipped' => (bool)($menuSeedResult['skipped'] ?? false),
                ],
                'summary' => [
                    'added' => (int)($result['added'] ?? 0),
                    'updated' => (int)($result['updated'] ?? 0),
                    'disabled' => (int)($result['disabled'] ?? 0),
                    'touched' => (int)($result['touched'] ?? 0),
                ],
                'details' => [
                    'added_nodes' => $details ? (array)($result['added_nodes'] ?? []) : [],
                    'updated_nodes' => $details ? (array)($result['updated_nodes'] ?? []) : [],
                    'disabled_nodes' => $details ? (array)($result['disabled_nodes'] ?? []) : [],
                    'details_included' => $details,
                ],
            ];
            $this->line(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            if ($exitCodeFlag) {
                exit($exitCode);
            }
            return;
        }

        if ($menuSeedResult !== null) {
            $this->line(sprintf(
                'Menu seed %s: added %d, updated %d, touched %d',
                $dryRun ? 'dry run' : 'sync',
                $menuSeedResult['added'],
                $menuSeedResult['updated'],
                $menuSeedResult['touched']
            ));
        }

        $this->line(sprintf(
            '%s: added %d, updated %d, disabled %d, touched %d',
            $dryRun ? 'Dry run complete' : 'Sync complete',
            $result['added'],
            $result['updated'],
            $result['disabled'],
            $result['touched']
        ));

        if ($details) {
            $this->line('--- Added Nodes ---');
            foreach ($result['added_nodes'] as $node) {
                $this->line((string)$node);
            }
            $this->line('--- Updated Nodes ---');
            foreach ($result['updated_nodes'] as $node) {
                $this->line((string)$node);
            }
            $this->line('--- Disabled Nodes ---');
            foreach ($result['disabled_nodes'] as $node) {
                $this->line((string)$node);
            }
        }

        if ($exitCodeFlag) {
            exit($exitCode);
        }
    }
}
