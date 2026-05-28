<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace System\Model;

use Carbon\Carbon;
use Hyperf\Database\Model\Collection;
use Hyperf\Database\Model\Relations\BelongsTo;
use Hyperf\Database\Model\Relations\HasMany;
use Hyperf\Database\Model\SoftDeletes;
use Library\Constants\Status;
use Library\CoreModel;

/**
 * @property int $id 主键ID
 * @property int $pid 父级字典ID，0表示字典分类
 * @property string $code 字典编码，分类编码全局唯一，字典项编码在同分类下唯一
 * @property string $name 字典名称
 * @property string $value 字典值，字典项在同分类下唯一
 * @property int $sort 排序权重
 * @property int $status 状态(1启用,0禁用)
 * @property string $remark 备注
 * @property int $created_by 创建者
 * @property int $updated_by 更新者
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 * @property string $deleted_at 删除时间
 * @property-read null|self $parent
 * @property-read null|Collection|self[] $children
 * @property array $extra 扩展配置JSON
 * @property-read string $status_text
 */
final class SystemDict extends CoreModel
{
    use SoftDeletes;

    protected array $fillable = ['id', 'pid', 'code', 'name', 'value', 'extra', 'sort', 'status', 'remark', 'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at'];

    protected array $casts = ['id' => 'integer', 'pid' => 'integer', 'sort' => 'integer', 'status' => 'integer', 'created_by' => 'integer', 'updated_by' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];

    protected array $logRules = [
        'name' => '系统字典',
        'title' => 'name',
        'ignore' => ['extra'],
        'fields' => [
            'pid' => '父级字典',
            'code' => '字典编码',
            'name' => '字典名称',
            'value' => '字典值',
            'sort' => '排序',
            'status' => ['name' => '状态', 'values' => [Status::DISABLED => '禁用', Status::ENABLED => '启用']],
            'remark' => '备注',
        ],
    ];

    /**
     * 字典项所属分类。
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'pid', 'id');
    }

    /**
     * 字典分类下的字典项。
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'pid', 'id')
            ->orderBy('sort', 'desc')
            ->orderBy('id', 'asc');
    }

    /**
     * 获取扩展配置时统一转为数组。
     */
    public function getExtraAttribute(mixed $value): array
    {
        return $this->_toArray($value);
    }

    /**
     * 保存扩展配置时统一写为 JSON。
     */
    public function setExtraAttribute(mixed $value): void
    {
        $this->_toJson($value, 'extra');
    }

    /**
     * 字典状态文本访问器。
     */
    public function getStatusTextAttribute(): string
    {
        return Status::getText((int)$this->status);
    }
}
