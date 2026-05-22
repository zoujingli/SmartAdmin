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
use Library\Constants\Status;
use Library\CoreMapper;
use System\Model\SystemDict;

final class DictMapper extends CoreMapper
{
    /**
     * @param string $model 字典模型类
     */
    public function __construct(
        protected string $model = SystemDict::class
    ) {}

    /**
     * 字典是平台全局配置，拥有管理权限即可查看完整列表，不按创建人做数据范围切分。
     */
    public function getPageList(?array $params, bool $isScope = true, string $pageName = 'page'): array
    {
        return parent::getPageList($params, false, $pageName);
    }

    /**
     * 字典是平台全局配置，拥有管理权限即可查看完整列表，不按创建人做数据范围切分。
     */
    public function getDataList(?array $params, bool $isScope = true): array
    {
        return parent::getDataList($params, false);
    }

    /**
     * 获取字典树，供父级选择和管理页面展示。
     */
    public function getTree(array $params = []): array
    {
        $query = $this->model::query();
        $query = $this->handleSearch($query, $params);

        return $query->orderBy('sort', 'desc')
            ->orderBy('id', 'asc')
            ->get()
            ->toArray();
    }

    /**
     * 检查同一层级下字段是否重复。
     */
    public function existsSiblingField(int $pid, string $field, mixed $value, int $excludeId = 0): bool
    {
        $query = $this->model::query()
            ->where('pid', $pid)
            ->where($field, $value);
        if ($excludeId > 0) {
            $query->where('id', '<>', $excludeId);
        }

        return $query->exists();
    }

    /**
     * 检查字典分类是否仍存在子项，含回收站数据，避免彻底删除后留下孤儿项。
     */
    public function hasChildren(int $id): bool
    {
        return $this->model::withTrashed()->where('pid', $id)->exists();
    }

    /**
     * 读取启用状态的字典分类。
     */
    public function findEnabledCategoryByCode(string $code): ?SystemDict
    {
        /** @var null|SystemDict $dict */
        $dict = $this->model::query()
            ->where('pid', 0)
            ->where('code', $code)
            ->where('status', Status::ENABLED)
            ->first();

        return $dict;
    }

    /**
     * 获取启用字典项选项。
     *
     * @return array<int, array{label:string,value:string,code:string,name:string,extra:array}>
     */
    public function getOptionsByCategory(SystemDict $category, array $params = []): array
    {
        $keyword = trim((string)($params['keyword'] ?? ''));
        $limit = max(1, min((int)($params['limit'] ?? 200), 500));
        $query = $this->model::query()
            ->where('pid', (int)$category->id)
            ->where('status', Status::ENABLED);

        if ($keyword !== '') {
            $query->where(function (Builder $subQuery) use ($keyword): void {
                $like = "%{$keyword}%";
                $subQuery->where('code', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('value', 'like', $like);
            });
        }

        return $query->orderBy('sort', 'desc')
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->get(['code', 'name', 'value', 'extra'])
            ->map(static fn (SystemDict $item): array => [
                'label' => (string)$item->name,
                'value' => (string)$item->value,
                'code' => (string)$item->code,
                'name' => (string)$item->name,
                'extra' => (array)$item->extra,
            ])
            ->toArray();
    }

    /**
     * 获取字典统计信息。
     */
    public function getStatistics(array $params = []): array
    {
        return $this->buildStatusStatisticsSummary($params, false);
    }

    /**
     * 字典读写不按创建人做数据范围切分，权限边界由 system.dict.* 控制。
     */
    protected function isOperationScopeEnabled(): bool
    {
        return false;
    }

    /**
     * 字典列表筛选条件。
     */
    protected function handleSearch(Builder $query, array $params): Builder
    {
        $builder = _query($query, $params)
            ->like('name,code,value')
            ->equal('pid,status')
            ->dateBetween('created_at')
            ->getQuery();

        $keyword = trim((string)($params['keyword'] ?? ''));
        if ($keyword !== '') {
            $builder->where(function (Builder $subQuery) use ($keyword): void {
                $like = "%{$keyword}%";
                $subQuery->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like)
                    ->orWhere('value', 'like', $like);
            });
        }

        return $builder;
    }

    /**
     * 字典列表扩展统计。
     */
    protected function handleListExtra(array $params = [], bool $isScope = true): array
    {
        return $this->buildStatusListExtra($params, false);
    }
}
