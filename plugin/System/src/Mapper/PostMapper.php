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
use Library\Constants\Status;
use Library\CoreMapper;
use System\Model\SystemPost;

final class PostMapper extends CoreMapper
{
    /**
     * @param string $model 岗位模型类
     */
    public function __construct(
        protected string $model = SystemPost::class
    ) {}

    /**
     * 获取启用状态的岗位选项。
     */
    public function getNormalOptions(array $params = []): array
    {
        $query = $this->model::where('status', Status::ENABLED);
        $query = $this->applyRequestedTenantScope($query, $params);
        $query = $this->applyDataScope($query, 'created_by');

        return $query->orderBy('sort', 'desc')
            ->orderBy('id', 'desc')
            ->get(['id', 'name'])
            ->toArray();
    }

    /**
     * 构建岗位统计信息。
     */
    public function getStatistics(array $params = []): array
    {
        return $this->buildStatusStatisticsSummary($params);
    }

    /**
     * 应用岗位列表筛选条件。
     */
    protected function handleSearch(Builder $query, array $params): Builder
    {
        $builder = _query($query, $params)
            ->like('name,code')
            ->equal('status')
            ->dateBetween('created_at')
            ->getQuery();

        $keyword = trim((string)($params['keyword'] ?? ''));
        if ($keyword !== '') {
            $builder->where(function (Builder $subQuery) use ($keyword) {
                $like = "%{$keyword}%";
                $subQuery->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like);
            });
        }

        return $builder;
    }

    /**
     * 构建岗位列表扩展统计。
     */
    protected function handleListExtra(array $params = [], bool $isScope = true): array
    {
        return $this->buildStatusListExtra($params, $isScope);
    }
}
