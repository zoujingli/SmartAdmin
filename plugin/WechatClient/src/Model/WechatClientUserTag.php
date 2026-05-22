<?php

declare(strict_types=1);

namespace Plugin\WechatClient\Model;

use Hyperf\Database\Model\SoftDeletes;
use Library\CoreModel;

/**
 * @property int $id 主键ID
 * @property int $status 状态(1启用,0禁用)
 * @property int $tenant_id 租户ID
 * @property int $account_id 接口账号ID
 * @property string $appid 微信 AppID
 * @property int $tag_id 微信标签ID
 * @property string $name 标签名称
 * @property int $count 粉丝数量
 * @property int $created_by 创建者
 * @property int $updated_by 更新者
 * @property \Carbon\Carbon $created_at 创建时间
 * @property \Carbon\Carbon $updated_at 更新时间
 * @property string $deleted_at 删除时间
 */
final class WechatClientUserTag extends CoreModel
{
    use SoftDeletes;

    /**
     * 微信模块模型显式声明表名，避免类名简化后与数据库命名规则产生偏差。
     */
    protected ?string $table = 'wechat_client_user_tag';

    protected array $fillable = ['id', 'status', 'tenant_id', 'account_id', 'appid', 'tag_id', 'name', 'count', 'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at'];

    protected array $casts = ['id' => 'integer', 'status' => 'integer', 'tenant_id' => 'integer', 'account_id' => 'integer', 'tag_id' => 'integer', 'count' => 'integer', 'created_by' => 'integer', 'updated_by' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];
}
