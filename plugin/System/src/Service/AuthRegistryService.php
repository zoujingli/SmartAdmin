<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace System\Service;

use Hyperf\DbConnection\Db;
use Library\Constants\Status;
use Library\Events\Annotation\Auth;
use Library\Events\Processor\AuthProcessor;
use System\Model\SystemMenu;
use System\Model\SystemNode;
use System\Model\SystemUser;
use System\Support\SystemNodeRegistry;

/**
 * 鉴权注册表同步服务。
 * 负责将菜单编码与 `#[Auth]` 注解统一汇总到 `system_node`。
 */
final class AuthRegistryService
{
    /**
     * 执行鉴权节点同步并返回详细报告。
     *
     * @return array{
     *   added:int,
     *   updated:int,
     *   disabled:int,
     *   touched:int,
     *   added_nodes:array<int, string>,
     *   updated_nodes:array<int, string>,
     *   disabled_nodes:array<int, string>
     * }
     */
    public function syncWithReport(bool $syncAnnotation = true, bool $syncMenu = true, bool $dryRun = false): array
    {
        $now = $this->currentTimestamp();
        $actorId = $this->resolveActorId();
        $desired = $this->buildDesiredNodes($syncAnnotation, $syncMenu);
        $desiredNodes = array_keys($desired);
        $existing = $this->loadExistingNodes();
        $diff = $this->diffDesiredNodes($desired, $existing);
        $addedNodes = array_keys($diff['missing']);
        $updatedNodes = array_keys($diff['dirty']);
        $disabledNodes = $this->resolveDisabledNodes(
            $this->resolveSyncSources($syncAnnotation, $syncMenu),
            $desiredNodes
        );

        if ($dryRun) {
            return $this->buildReport(
                count($addedNodes),
                count($updatedNodes),
                count($disabledNodes),
                count($desiredNodes),
                $addedNodes,
                $updatedNodes,
                $disabledNodes,
            );
        }

        Db::beginTransaction();
        try {
            $added = 0;

            foreach ($diff['missing'] as $node => $row) {
                SystemNode::query()->create($this->makeCreatePayload($node, $row, $actorId, $now));
                ++$added;
            }

            $updated = 0;
            foreach ($diff['dirty'] as $change) {
                SystemNode::query()
                    ->where('id', $change['id'])
                    ->update($this->makeUpdatePayload($change['row'], $actorId, $now));
                ++$updated;
            }

            $disabled = $this->disableNodes($disabledNodes, $actorId, $now);

            Db::commit();

            return $this->buildReport(
                $added,
                $updated,
                $disabled,
                count($desiredNodes),
                $addedNodes,
                $updatedNodes,
                $disabledNodes,
            );
        } catch (\Throwable $e) {
            Db::rollBack();
            throw $e;
        }
    }

    /**
     * 直接执行同步并返回汇总结果。
     */
    public function sync(bool $syncAnnotation = true, bool $syncMenu = true): array
    {
        $result = $this->syncWithReport($syncAnnotation, $syncMenu, false);

        return [
            'added' => $result['added'],
            'updated' => $result['updated'],
            'disabled' => $result['disabled'],
            'touched' => $result['touched'],
        ];
    }

    /**
     * 构建期望的鉴权节点快照。
     *
     * @return array<string, array{name:string,type:string,source:string,ref:string,status:int,meta:string}>
     */
    private function buildDesiredNodes(bool $syncAnnotation, bool $syncMenu): array
    {
        $result = [];

        if ($syncAnnotation) {
            $list = AuthProcessor::getAuthList(true);
            foreach ($list as $item) {
                $prepared = $this->buildAnnotationNode($item);
                if ($prepared === null) {
                    continue;
                }

                $result[$prepared['node']] = $prepared['row'];
            }
        }

        if ($syncMenu) {
            $menus = SystemMenu::query()
                ->where('code', '!=', '')
                ->get(['id', 'name', 'code', 'type', 'status']);

            foreach ($menus as $menu) {
                $prepared = $this->buildMenuNode($menu);
                if ($prepared === null) {
                    continue;
                }

                $node = $prepared['node'];

                if (isset($result[$node])) {
                    $result[$node]['name'] = (string)($menu->name ?? $result[$node]['name'] ?? '');
                    $result[$node]['meta'] = $this->mergeMeta(
                        (string)($result[$node]['meta'] ?? ''),
                        SystemNodeRegistry::menuMeta(
                            (int)($menu->id ?? 0),
                            (string)($menu->type ?? ''),
                            (int)($menu->status ?? Status::ENABLED),
                        )
                    );
                    continue;
                }

                $result[$node] = $prepared['row'];
            }
        }

        return $result;
    }

    /**
     * 加载当前注册表中的全部节点，便于做增量比对。
     *
     * @return array<string, SystemNode>
     */
    private function loadExistingNodes(): array
    {
        return SystemNode::query()
            ->get(['id', 'node', 'name', 'type', 'source', 'ref', 'status', 'meta'])
            ->keyBy('node')
            ->all();
    }

    /**
     * @param array<string, array{name:string,type:string,source:string,ref:string,status:int,meta:string}> $desired
     * @param array<string, SystemNode> $existing
     * @return array{
     *   missing: array<string, array{name:string,type:string,source:string,ref:string,status:int,meta:string}>,
     *   dirty: array<string, array{id:int,row:array{name:string,type:string,source:string,ref:string,status:int,meta:string}}>
     * }
     */
    private function diffDesiredNodes(array $desired, array $existing): array
    {
        $missing = [];
        $dirty = [];

        foreach ($desired as $node => $row) {
            $exists = $existing[$node] ?? null;
            if (!$exists instanceof SystemNode) {
                $missing[$node] = $row;
                continue;
            }

            if ($this->isNodeRowDirty($exists, $row)) {
                $dirty[$node] = [
                    'id' => (int)$exists->id,
                    'row' => $row,
                ];
            }
        }

        return [
            'missing' => $missing,
            'dirty' => $dirty,
        ];
    }

    /**
     * @param array{name:string,type:string,source:string,ref:string,status:int,meta:string} $row
     */
    private function isNodeRowDirty(SystemNode $exists, array $row): bool
    {
        foreach (SystemNodeRegistry::SYNC_FIELDS as $field) {
            if ((string)($exists->{$field} ?? '') !== (string)($row[$field] ?? '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * 解析本轮需要参与“停用比对”的来源集合。
     *
     * @return array<int, string>
     */
    private function resolveSyncSources(bool $syncAnnotation, bool $syncMenu): array
    {
        $sources = [];
        if ($syncAnnotation) {
            $sources[] = SystemNodeRegistry::SOURCE_ANNOTATION;
        }
        if ($syncMenu) {
            $sources[] = SystemNodeRegistry::SOURCE_MENU;
        }

        return $sources;
    }

    /**
     * 计算需要从启用态切换为停用态的节点。
     *
     * @param array<int, string> $sources
     * @param array<int, string> $desiredNodes
     * @return array<int, string>
     */
    private function resolveDisabledNodes(array $sources, array $desiredNodes): array
    {
        if ($sources === []) {
            return [];
        }

        return SystemNode::query()
            ->whereIn('source', $sources)
            ->whereNotIn('node', $desiredNodes)
            ->where('status', Status::ENABLED)
            ->pluck('node')
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * 构建新增节点写入载荷。
     *
     * @param array{name:string,type:string,source:string,ref:string,status:int,meta:string} $row
     * @return array<string, int|string>
     */
    private function makeCreatePayload(string $node, array $row, int $actorId, string $now): array
    {
        return SystemNodeRegistry::record(
            $node,
            (string)$row['name'],
            (string)$row['type'],
            (string)$row['source'],
            (string)$row['ref'],
            $this->decodeMeta((string)$row['meta']),
            (int)$row['status'],
            $actorId,
            $now
        );
    }

    /**
     * 构建已有节点更新载荷。
     *
     * @param array{name:string,type:string,source:string,ref:string,status:int,meta:string} $row
     * @return array<string, int|string>
     */
    private function makeUpdatePayload(array $row, int $actorId, string $now): array
    {
        return $row + [
            'updated_by' => $actorId,
            'updated_at' => $now,
        ];
    }

    /**
     * 批量停用已失效节点。
     *
     * @param array<int, string> $disabledNodes
     */
    private function disableNodes(array $disabledNodes, int $actorId, string $now): int
    {
        if ($disabledNodes === []) {
            return 0;
        }

        return (int)SystemNode::query()
            ->whereIn('node', $disabledNodes)
            ->update([
                'status' => Status::DISABLED,
                'updated_by' => $actorId,
                'updated_at' => $now,
            ]);
    }

    /**
     * 将注解扫描结果转换为标准节点载荷。
     *
     * @param array<string, mixed> $item
     * @return null|array{
     *   node:string,
     *   row:array{name:string,type:string,source:string,ref:string,status:int,meta:string}
     * }
     */
    private function buildAnnotationNode(array $item): ?array
    {
        $access = $item['access'] ?? [];
        $node = (string)($access['node'] ?? '');
        if ($node === '') {
            return null;
        }
        $userModel = (string)($access['userModel'] ?? SystemUser::class);
        if ($userModel !== SystemUser::class) {
            // system_node 只维护后台 RBAC 节点；ProjectAccount 等前台用户体系的权限不进入后台角色授权表。
            return null;
        }

        return [
            'node' => $node,
            'row' => SystemNodeRegistry::payload(
                (string)($access['name'] ?? ''),
                (string)($access['type'] ?? Auth::CHECK),
                SystemNodeRegistry::SOURCE_ANNOTATION,
                (string)(($item['class'] ?? '') . '@' . ($item['method'] ?? '')),
                ['menu' => (bool)($access['menu'] ?? false)],
            ),
        ];
    }

    /**
     * @return null|array{
     *   node:string,
     *   row:array{name:string,type:string,source:string,ref:string,status:int,meta:string}
     * }
     */
    private function buildMenuNode(SystemMenu $menu): ?array
    {
        $node = (string)($menu->code ?? '');
        if ($node === '') {
            return null;
        }

        return [
            'node' => $node,
            'row' => SystemNodeRegistry::payload(
                (string)($menu->name ?? ''),
                '',
                SystemNodeRegistry::SOURCE_MENU,
                'menu:' . (string)($menu->id ?? 0),
                SystemNodeRegistry::menuMeta(
                    (int)($menu->id ?? 0),
                    (string)($menu->type ?? ''),
                    (int)($menu->status ?? Status::ENABLED),
                ),
            ),
        ];
    }

    /**
     * 解码节点元数据 JSON。
     *
     * @return array<string, bool|int|string>
     */
    private function decodeMeta(string $meta): array
    {
        $decoded = json_decode($meta, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * 合并节点元数据并保持统一编码规则。
     *
     * @param array<string, bool|int|string> $meta
     */
    private function mergeMeta(string $currentMeta, array $meta): string
    {
        return SystemNodeRegistry::mergeMeta($currentMeta, $meta);
    }

    /**
     * 解析同步操作者 ID；无登录上下文时返回 0（系统任务）。
     */
    private function resolveActorId(): int
    {
        return user()?->getId() ?? 0;
    }

    /**
     * 获取当前同步统一时间戳，确保同一轮写入时间一致。
     */
    private function currentTimestamp(): string
    {
        return date('Y-m-d H:i:s');
    }

    /**
     * 构建鉴权同步结果报告。
     *
     * @param array<int, string> $addedNodes
     * @param array<int, string> $updatedNodes
     * @param array<int, string> $disabledNodes
     * @return array{
     *   added:int,
     *   updated:int,
     *   disabled:int,
     *   touched:int,
     *   added_nodes:array<int, string>,
     *   updated_nodes:array<int, string>,
     *   disabled_nodes:array<int, string>
     * }
     */
    private function buildReport(
        int $added,
        int $updated,
        int $disabled,
        int $touched,
        array $addedNodes,
        array $updatedNodes,
        array $disabledNodes
    ): array {
        return [
            'added' => $added,
            'updated' => $updated,
            'disabled' => $disabled,
            'touched' => $touched,
            'added_nodes' => $addedNodes,
            'updated_nodes' => $updatedNodes,
            'disabled_nodes' => $disabledNodes,
        ];
    }
}
