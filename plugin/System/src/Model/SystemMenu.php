<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://github.com/zoujingli/SmartAdmin/blob/master/readme.md
 */

namespace System\Model;

use Carbon\Carbon;
use Hyperf\Database\Model\Collection;
use Hyperf\Database\Model\Relations\BelongsTo;
use Hyperf\Database\Model\Relations\HasMany;
use Hyperf\Database\Model\Relations\HasOne;
use Hyperf\Database\Model\SoftDeletes;
use Library\Constants\MenuType;
use Library\Constants\Status;
use Library\CoreModel;
use Library\Traits\ModelDeptTrait;

/**
 * @property int $id 主键ID
 * @property int $pid 父级菜单ID
 * @property string $level 层级路径
 * @property string $name 菜单名称
 * @property string $code 菜单权限编码
 * @property string $icon 菜单图标
 * @property string $type 菜单类型(D目录,M菜单,B按钮,L外链,I内嵌页)
 * @property string $route 前端路由地址
 * @property string $component 前端路由组件
 * @property string $redirect 重定向地址
 * @property string $link 外部链接
 * @property string $iframe_src 内嵌页面地址
 * @property int $hide_in_menu 是否隐藏菜单(1隐藏,0显示)
 * @property int $hide_in_breadcrumb 是否隐藏面包屑(1隐藏,0显示)
 * @property int $hide_in_tab 是否隐藏标签页(1隐藏,0显示)
 * @property int $keep_alive 是否缓存组件(1缓存,0不缓存)
 * @property int $affix_tab 是否固定标签页(1固定,0不固定)
 * @property int $sort 排序权重
 * @property int $status 状态(1启用,0禁用)
 * @property string $remark 备注
 * @property int $created_by 创建者
 * @property int $updated_by 更新者
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 * @property string $deleted_at 删除时间
 * @property-read string $full_path 
 * @property-read int $depth 
 * @property-read null|SystemMenu $parent 
 * @property-read null|Collection|SystemMenu[] $children 
 * @property-read null|SystemNode $node 
 * @property-read string $type_text 
 * @property-read string $status_text 
 */
final class SystemMenu extends CoreModel
{
    use SoftDeletes;
    use ModelDeptTrait;

    public const TYPE_PATH = MenuType::PATH;

    public const TYPE_MENU = MenuType::MENU;

    public const TYPE_BUTTON = MenuType::BUTTON;

    public const TYPE_LINK = MenuType::LINK;

    public const TYPE_IFRAME = MenuType::IFRAME;

    protected array $fillable = ['id', 'pid', 'level', 'name', 'code', 'icon', 'type', 'route', 'component', 'redirect', 'link', 'iframe_src', 'hide_in_menu', 'hide_in_breadcrumb', 'hide_in_tab', 'keep_alive', 'affix_tab', 'sort', 'status', 'remark', 'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at'];

    protected array $casts = ['id' => 'integer', 'pid' => 'integer', 'hide_in_menu' => 'integer', 'hide_in_breadcrumb' => 'integer', 'hide_in_tab' => 'integer', 'keep_alive' => 'integer', 'affix_tab' => 'integer', 'sort' => 'integer', 'status' => 'integer', 'created_by' => 'integer', 'updated_by' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];

    protected array $logRules = [
        'name' => '系统菜单',
        'title' => 'name',
        'fields' => [
            'pid' => '父级菜单',
            'name' => '菜单名称',
            'code' => '菜单编码',
            'icon' => '菜单图标',
            'type' => [
                'name' => '菜单类型',
                'values' => [
                    MenuType::PATH => '目录',
                    MenuType::MENU => '菜单',
                    MenuType::BUTTON => '按钮',
                    MenuType::LINK => '链接',
                    MenuType::IFRAME => '内嵌',
                ],
            ],
            'route' => '路由地址',
            'component' => '路由组件',
            'redirect' => '重定向地址',
            'link' => '外部链接',
            'iframe_src' => '内嵌地址',
            'hide_in_menu' => ['name' => '隐藏菜单', 'values' => [0 => '否', 1 => '是']],
            'hide_in_breadcrumb' => ['name' => '隐藏面包屑', 'values' => [0 => '否', 1 => '是']],
            'hide_in_tab' => ['name' => '隐藏标签页', 'values' => [0 => '否', 1 => '是']],
            'keep_alive' => ['name' => '缓存组件', 'values' => [0 => '否', 1 => '是']],
            'affix_tab' => ['name' => '固定标签页', 'values' => [0 => '否', 1 => '是']],
            'sort' => '排序',
            'status' => ['name' => '状态', 'values' => [Status::DISABLED => '禁用', Status::ENABLED => '启用']],
            'remark' => '备注',
        ],
    ];

    /**
     * 父级菜单关联。
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(SystemMenu::class, 'pid', 'id');
    }

    /**
     * 子级菜单集合。
     */
    public function children(): HasMany
    {
        return $this->hasMany(SystemMenu::class, 'pid', 'id')
            ->orderBy('sort', 'desc')
            ->orderBy('id', 'asc');
    }

    /**
     * 关联权限节点记录。
     */
    public function node(): HasOne
    {
        return $this->hasOne(SystemNode::class, 'node', 'code');
    }

    /**
     * 菜单类型文本访问器。
     */
    public function getTypeTextAttribute(): string
    {
        return MenuType::getText((string)$this->type);
    }

    /**
     * 菜单状态文本访问器。
     */
    public function getStatusTextAttribute(): string
    {
        return Status::getText((int)$this->status);
    }
}
