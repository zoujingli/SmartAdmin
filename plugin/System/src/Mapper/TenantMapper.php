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
use Library\CoreMapper;
use System\Model\SystemTenant;

final class TenantMapper extends CoreMapper
{
    public function __construct(
        protected string $model = SystemTenant::class
    ) {}

    public function getStatistics(array $params = []): array
    {
        return $this->buildStatusStatisticsSummary($params);
    }

    /**
     * 获取启用租户选项，供平台账号创建用户时选择租户空间。
     */
    public function getNormalOptions(array $params = []): array
    {
        $keyword = trim((string)($params['keyword'] ?? ''));
        $limit = max(1, min((int)($params['limit'] ?? 100), 500));
        $query = $this->model::query()->where('status', 1);

        if ($keyword !== '') {
            $query->where(function (Builder $subQuery) use ($keyword): void {
                $like = "%{$keyword}%";
                $subQuery->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like);
            });
        }

        return $query->orderBy('id', 'desc')
            ->limit($limit)
            ->get(['id', 'code', 'name'])
            ->map(static fn (SystemTenant $tenant): array => [
                'id' => (int)$tenant->id,
                'code' => (string)$tenant->code,
                'name' => (string)$tenant->name,
                'label' => sprintf('%s（%s）', (string)$tenant->name, (string)$tenant->code),
            ])
            ->toArray();
    }

    protected function handleSearch(Builder $query, array $params): Builder
    {
        $builder = _query($query, $params)
            ->like('name,code,contact_name,contact_phone,contact_email,package_code,remark')
            ->equal('status')
            ->dateBetween('created_at')
            ->getQuery();

        $keyword = trim((string)($params['keyword'] ?? ''));
        if ($keyword !== '') {
            $builder->where(function (Builder $subQuery) use ($keyword) {
                $like = "%{$keyword}%";
                $subQuery->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like)
                    ->orWhere('contact_name', 'like', $like)
                    ->orWhere('contact_phone', 'like', $like)
                    ->orWhere('contact_email', 'like', $like)
                    ->orWhere('package_code', 'like', $like)
                    ->orWhere('remark', 'like', $like);
            });
        }

        return $builder;
    }

    protected function handleListExtra(array $params = [], bool $isScope = true): array
    {
        return $this->buildStatusListExtra($params, $isScope);
    }
}
