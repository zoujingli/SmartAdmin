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
use System\Model\SystemFile;

final class FileMapper extends CoreMapper
{
    /**
     * @param string $model 文件模型类
     */
    public function __construct(
        protected string $model = SystemFile::class
    ) {}

    /**
     * 过滤查询条件.
     */
    protected function handleSearch(Builder $query, array $params): Builder
    {
        return _query($query, $params)
            ->like('origin_name,hash,storage_path,object_name,mime_type,remark')
            ->equal('scene,driver,storage_mode,suffix')
            ->dateBetween('created_at')
            ->getQuery();
    }

    /**
     * @param array<int, \Hyperf\Database\Model\Model|array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    protected function handleListItems(array $items, array $params = []): array
    {
        return array_map(static function ($item): array {
            $row = is_array($item) ? $item : $item->toArray();
            if ($item instanceof SystemFile) {
                $deletedAt = $item->deleted_at;
                $row['deleted_at'] = $deletedAt instanceof \DateTimeInterface ? $deletedAt->format('Y-m-d H:i:s') : null;
            } else {
                $row['deleted_at'] = $row['deleted_at'] ?? null;
            }
            return $row;
        }, $items);
    }

    /**
     * @return array<string, mixed>
     */
    public function getStatistics(array $params = []): array
    {
        $query = $this->makeStatsQuery($params, true);

        return [
            'total' => (int)$query->clone()->count(),
            'today_uploaded' => $this->countToday($query),
            'total_size_byte' => (int)$query->clone()->sum('size_byte'),
            'by_scene' => $this->pluckGroupedCounts($query, 'scene'),
            'by_driver' => $this->pluckGroupedCounts($query, 'driver'),
            'by_storage_mode' => $this->pluckGroupedCounts($query, 'storage_mode'),
        ];
    }
}
