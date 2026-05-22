<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://github.com/zoujingli/SmartAdmin/blob/master/readme.md
 */

namespace System\Mapper;

use Hyperf\Database\Model\Builder;
use Hyperf\Database\Model\Model;
use Library\CoreMapper;
use System\Model\SystemLogsChange;

final class LogsChangeMapper extends CoreMapper
{
    /**
     * @param string $model 变更日志模型类
     */
    public function __construct(
        protected string $model = SystemLogsChange::class
    ) {}

    /**
     * 写入变更日志。
     *
     * 变更日志来自操作日志采集链路，不使用通用 CoreMapper::create，避免触发模型变更兜底记录自身。
     */
    public function create(array $data): Model
    {
        $modelClass = $this->model;

        return $modelClass::query()->create($this->filterLogPayload($data));
    }

    /**
     * 获取指定操作日志下的业务变更明细；默认按 created_by 数据范围 fail closed。
     */
    public function getByActionId(int $actionId, bool $isScope = true): array
    {
        if ($actionId <= 0) {
            return [];
        }

        return $this->getQuery($isScope)
            ->where('action_id', $actionId)
            ->orderBy('id')
            ->get()
            ->toArray();
    }

    /**
     * 变更日志独立列表筛选；只开放固定白名单字段，避免前端传入任意查询表达式。
     */
    protected function handleSearch(Builder $query, array $params): Builder
    {
        $builder = _query($query, $params)
            ->like('username,model_name,record_id,record_label,change_remark')
            ->equal('tenant_id,action_id,model,table_name,event')
            ->dateBetween('created_at')
            ->getQuery();

        $keyword = trim((string)($params['keyword'] ?? ''));
        if ($keyword !== '') {
            $builder->where(function (Builder $subQuery) use ($keyword): void {
                $like = "%{$keyword}%";
                $subQuery->where('username', 'like', $like)
                    ->orWhere('model', 'like', $like)
                    ->orWhere('table_name', 'like', $like)
                    ->orWhere('model_name', 'like', $like)
                    ->orWhere('record_id', 'like', $like)
                    ->orWhere('record_label', 'like', $like)
                    ->orWhere('event', 'like', $like)
                    ->orWhere('change_remark', 'like', $like);
            });
        }

        return $builder;
    }

    /**
     * 变更日志列表摘要只统计当前筛选范围，供前端看板和导出确认使用。
     *
     * @return array<string, mixed>
     */
    protected function handleListExtra(array $params = [], bool $isScope = true): array
    {
        return [
            'statistics' => $this->getStatistics($params, $isScope),
        ];
    }

    /**
     * 获取变更日志统计信息。
     *
     * @return array<string, mixed>
     */
    public function getStatistics(array $params = [], bool $isScope = true): array
    {
        $query = $this->makeStatsQuery($params, $isScope);
        $total = (int)$query->clone()->count();

        return [
            'total' => $total,
            'today' => (int)$query->clone()->whereDate('created_at', date('Y-m-d'))->count(),
            'by_event' => self::normalizeCountMap($query->clone()
                ->selectRaw('event, COUNT(*) as aggregate')
                ->groupBy('event')
                ->pluck('aggregate', 'event')
                ->toArray()),
            'by_model' => self::normalizeCountMap($query->clone()
                ->selectRaw('model_name, COUNT(*) as aggregate')
                ->groupBy('model_name')
                ->pluck('aggregate', 'model_name')
                ->toArray()),
            'by_table' => self::normalizeCountMap($query->clone()
                ->selectRaw('table_name, COUNT(*) as aggregate')
                ->groupBy('table_name')
                ->pluck('aggregate', 'table_name')
                ->toArray()),
        ];
    }

    /**
     * 构建变更日志查询入口。
     */
    public function getQuery(bool $isScope = true, bool $withTrashed = false): Builder
    {
        $modelClass = $this->model;
        $query = $withTrashed ? $modelClass::withTrashed() : $modelClass::query();

        return $isScope ? $this->applyDataScope($query, 'created_by') : $query;
    }

    /**
     * @param array<int, int> $actionIds
     */
    public function softDeleteByActionIds(array $actionIds): void
    {
        $actionIds = $this->normalizeIds($actionIds);
        if ($actionIds === []) {
            return;
        }

        $this->getQuery(false)
            ->whereIn('action_id', $actionIds)
            ->delete();
    }

    /**
     * 按操作日志查询同步软删变更日志；用于按筛选条件清空 action 时避免孤立 change。
     */
    public function softDeleteByActionQuery(Builder $actionQuery): void
    {
        $this->getQuery(false)
            ->whereIn('action_id', $actionQuery->select('id'))
            ->delete();
    }

    /**
     * @param array<int, int> $actionIds
     */
    public function restoreByActionIds(array $actionIds): void
    {
        $actionIds = $this->normalizeIds($actionIds);
        if ($actionIds === []) {
            return;
        }

        $this->getQuery(false, true)
            ->onlyTrashed()
            ->whereIn('action_id', $actionIds)
            ->get()
            ->each(static function (SystemLogsChange $change): void {
                $change->restore();
            });
    }

    /**
     * @param array<int, int> $actionIds
     */
    public function forceDeleteByActionIds(array $actionIds): void
    {
        $actionIds = $this->normalizeIds($actionIds);
        if ($actionIds === []) {
            return;
        }

        $this->getQuery(false, true)
            ->whereIn('action_id', $actionIds)
            ->get()
            ->each(static function (SystemLogsChange $change): void {
                $change->forceDelete();
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function filterLogPayload(array $data): array
    {
        $model = $this->getModel();
        $allowed = array_fill_keys($model->getFillable(), true);
        foreach ([$model->getKeyName(), 'created_at', 'updated_at', 'deleted_at'] as $field) {
            unset($allowed[$field]);
        }

        return array_intersect_key($data, $allowed);
    }

    /**
     * 统计结果统一转成 string=>int，并把空分组归入“未记录”。
     *
     * @param array<int|string, mixed> $counts
     * @return array<string, int>
     */
    private static function normalizeCountMap(array $counts): array
    {
        $result = [];
        foreach ($counts as $key => $count) {
            $name = trim((string)$key);
            $result[$name === '' ? '未记录' : $name] = (int)$count;
        }

        return $result;
    }
}
