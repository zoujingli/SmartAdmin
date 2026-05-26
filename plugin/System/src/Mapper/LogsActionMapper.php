<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace System\Mapper;

use Hyperf\Database\Model\Builder;
use Hyperf\Database\Model\Model;
use Library\CoreMapper;
use System\Model\SystemLogsAction;

final class LogsActionMapper extends CoreMapper
{
    /**
     * @param string $model 日志模型类
     */
    public function __construct(
        protected string $model = SystemLogsAction::class
    ) {}

    /**
     * 写入操作日志。
     *
     * 通用 CoreMapper 会剔除 created_by/updated_by 防止外部请求伪造审计字段；
     * 操作日志的审计字段来自采集器，必须保留，但仍禁止写入主键和时间戳。
     */
    public function create(array $data): Model
    {
        $modelClass = $this->model;

        return $modelClass::query()->create($this->filterLogPayload($data));
    }

    /**
     * 清理日志数据。
     *
     * 当传入日期时，仅删除该日期之前的日志；未传入则按当前查询范围全量清理。
     */
    public function clear(?string $date = null, bool $isScope = true): int
    {
        return $this->makeClearQuery($date, $isScope)->delete();
    }

    /**
     * 构建清理操作日志的查询；Service 会复用同一查询先同步处理关联 change，再删除 action。
     */
    public function makeClearQuery(?string $date = null, bool $isScope = true): Builder
    {
        $query = $this->getQuery($isScope);
        if ($date !== null) {
            $query->where('created_at', '<', $date);
        }

        return $query;
    }

    /**
     * 软删除操作日志并同步处理关联变更日志。
     */
    public function delete(array $ids): bool
    {
        $ids = $this->normalizeIds($ids);
        if ($ids === []) {
            return true;
        }

        $models = $this->getOperationModels($ids);
        if (count($models) !== count($ids)) {
            return false;
        }

        foreach ($models as $model) {
            $model->delete();
        }

        return true;
    }

    /**
     * 彻底删除操作日志时一并彻底删除关联变更，防止 action_id 指向不存在的记录。
     */
    public function delreal(array $ids): bool
    {
        $ids = $this->normalizeIds($ids);
        if ($ids === []) {
            return true;
        }

        $models = $this->getOperationModels($ids, true);
        if (count($models) !== count($ids)) {
            return false;
        }

        foreach ($models as $model) {
            $model->forceDelete();
        }

        return true;
    }

    /**
     * 恢复操作日志时恢复同一 action_id 下的变更日志，保持详情闭环完整。
     */
    public function recovery(array $ids): bool
    {
        $ids = $this->normalizeIds($ids);
        if ($ids === []) {
            return true;
        }

        $models = $this->getOperationModels($ids, true);
        if (count($models) !== count($ids)) {
            return false;
        }

        foreach ($models as $model) {
            if (!method_exists($model, 'restore')) {
                return false;
            }

            $model->restore();
        }

        return true;
    }

    /**
     * 构建日志基础查询（可选数据范围）。
     */
    public function getQuery(bool $isScope = true): Builder
    {
        $query = $this->model::query();

        return $isScope ? $this->applyDataScope($query, 'created_by') : $query;
    }

    /**
     * 构建日志统计查询入口。
     */
    public function makeLogQuery(array $params = [], bool $isScope = true): Builder
    {
        return $this->makeStatsQuery($params, $isScope);
    }

    /**
     * 日志列表查询条件。
     */
    protected function handleSearch(Builder $query, array $params): Builder
    {
        $builder = _query($query, $params)
            ->like('username,router,name,ip,remark')
            ->equal('method,response_code')
            ->dateBetween('created_at')
            ->getQuery();

        $keyword = trim((string)($params['keyword'] ?? ''));
        if ($keyword !== '') {
            $builder->where(function (Builder $subQuery) use ($keyword) {
                $like = "%{$keyword}%";
                $subQuery->where('username', 'like', $like)
                    ->orWhere('router', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('ip', 'like', $like)
                    ->orWhere('remark', 'like', $like);
            });
        }

        if (!empty($params['exclude_name']) && is_scalar($params['exclude_name'])) {
            $builder->where('name', '!=', (string)$params['exclude_name']);
        }

        return $builder;
    }

    /**
     * 日志列表扩展统计信息。
     */
    protected function handleListExtra(array $params = [], bool $isScope = true): array
    {
        try {
            $query = $this->makeStatsQuery($params, $isScope);
            $responseCodeStats = $this->pluckGroupedCounts($query, 'response_code');
            $total = (int)$query->count();
            $successCount = (int)($responseCodeStats['200'] ?? 0);
            $warningCount = 0;
            foreach ($responseCodeStats as $code => $count) {
                if (str_starts_with((string)$code, '4')) {
                    $warningCount += (int)$count;
                }
            }

            return [
                'statistics' => [
                    'total' => $total,
                    'today' => $this->countToday($query),
                    'by_response_code' => $responseCodeStats,
                    'success_count' => $successCount,
                    'warning_count' => $warningCount,
                    'error_count' => max(0, $total - $successCount - $warningCount),
                ],
            ];
        } catch (\Throwable) {
            return [
                'statistics' => [
                    'total' => 0,
                    'today' => 0,
                    'by_response_code' => [],
                    'success_count' => 0,
                    'warning_count' => 0,
                    'error_count' => 0,
                ],
            ];
        }
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
}
