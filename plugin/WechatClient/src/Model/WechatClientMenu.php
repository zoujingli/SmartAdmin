<?php

declare(strict_types=1);

namespace Plugin\WechatClient\Model;

use Hyperf\Database\Model\SoftDeletes;
use Library\CoreModel;

/**
 * @property int $id 主键ID
 * @property int $tenant_id 租户ID
 * @property int $account_id 接口账号ID
 * @property string $name 菜单方案名称
 * @property string $published_at 发布时间
 * @property string $publish_result 发布结果
 * @property int $status 状态(1启用,0禁用)
 * @property int $created_by 创建者
 * @property int $updated_by 更新者
 * @property \Carbon\Carbon $created_at 创建时间
 * @property \Carbon\Carbon $updated_at 更新时间
 * @property string $deleted_at 删除时间
 * @property array $buttons 菜单按钮 JSON
 */
final class WechatClientMenu extends CoreModel
{
    use SoftDeletes;

    /**
     * 微信模块模型显式声明表名，避免类名简化后与数据库命名规则产生偏差。
     */
    protected ?string $table = 'wechat_client_menu';

    protected array $fillable = ['id', 'tenant_id', 'account_id', 'name', 'buttons', 'published_at', 'publish_result', 'status', 'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at'];

    protected array $casts = ['id' => 'integer', 'tenant_id' => 'integer', 'account_id' => 'integer', 'status' => 'integer', 'created_by' => 'integer', 'updated_by' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];

    protected array $logRules = [
        'name' => '微信菜单',
        'title' => 'name',
        'ignore' => ['buttons', 'publish_result'],
        'fields' => [
            'account_id' => '接口账号 ID',
            'name' => '菜单方案名称',
            'published_at' => '发布时间',
            'status' => ['name' => '状态', 'values' => [0 => '禁用', 1 => '启用']],
        ],
    ];


    /**
     * 将菜单按钮结构反序列化为数组。
     */
    public function getButtonsAttribute(mixed $value): array
    {
        return $this->_toArray($value);
    }

    /**
     * 将菜单按钮结构序列化为 JSON 保存。
     */
    public function setButtonsAttribute(mixed $value): void
    {
        $this->_toJson($value, 'buttons');
    }
}
