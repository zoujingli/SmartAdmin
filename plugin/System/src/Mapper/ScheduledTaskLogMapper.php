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
use Library\CoreMapper;
use System\Model\SystemScheduledTaskLog;

final class ScheduledTaskLogMapper extends CoreMapper
{
    public function __construct(
        protected string $model = SystemScheduledTaskLog::class
    ) {}

    /**
     * 执行日志没有 created_by 字段，列表只使用 tenant_id 全局范围，避免把 task_id 误当作用户字段。
     */
    protected function makeFilteredQuery(?array $params, bool $isScope): Builder
    {
        $params ??= [];
        $modelClass = $this->model;
        $query = $modelClass::query();

        return $this->handleSearch($query, $params);
    }

    protected function handleSearch(Builder $query, array $params): Builder
    {
        $builder = _query($query, $params)
            ->like('task_name,task_code,owner_plugin,owner_type,owner_name,message')
            ->equal('task_id,task_code,status,trigger_type,owner_plugin,owner_type,owner_id')
            ->dateBetween('created_at,started_at')
            ->getQuery();

        $keyword = trim((string)($params['keyword'] ?? ''));
        if ($keyword !== '') {
            $builder->where(function (Builder $subQuery) use ($keyword): void {
                $like = "%{$keyword}%";
                $subQuery->where('task_name', 'like', $like)
                    ->orWhere('task_code', 'like', $like)
                    ->orWhere('owner_name', 'like', $like)
                    ->orWhere('message', 'like', $like);
            });
        }

        return $builder;
    }

    protected function applyOperationScope(Builder $query): Builder
    {
        // 执行日志没有创建人语义，后台查询只依赖 tenant_id 全局范围；无租户上下文时 CoreModel 会 fail closed。
        return $query;
    }
}
