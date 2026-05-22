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
 * @property string $openid 粉丝 OpenID
 * @property string $unionid UnionID
 * @property string $nickname 昵称
 * @property string $avatar 头像
 * @property int $subscribe 是否关注
 * @property string $subscribe_time 关注时间
 * @property string $remark 备注
 * @property int $created_by 创建者
 * @property int $updated_by 更新者
 * @property \Carbon\Carbon $created_at 创建时间
 * @property \Carbon\Carbon $updated_at 更新时间
 * @property string $deleted_at 删除时间
 * @property array $tagids 标签ID JSON
 * @property array $raw_payload 官方原始数据 JSON
 */
final class WechatClientUser extends CoreModel
{
    use SoftDeletes;

    /**
     * 微信模块模型显式声明表名，避免类名简化后与数据库命名规则产生偏差。
     */
    protected ?string $table = 'wechat_client_user';

    protected array $fillable = ['id', 'status', 'tenant_id', 'account_id', 'appid', 'openid', 'unionid', 'nickname', 'avatar', 'subscribe', 'subscribe_time', 'remark', 'tagids', 'raw_payload', 'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at'];

    protected array $casts = ['id' => 'integer', 'status' => 'integer', 'tenant_id' => 'integer', 'account_id' => 'integer', 'subscribe' => 'integer', 'created_by' => 'integer', 'updated_by' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];

    /**
     * 将微信粉丝标签 ID 列表反序列化为数组。
     */
    public function getTagidsAttribute(mixed $value): array
    {
        return $this->_toArray($value);
    }

    /**
     * 将微信粉丝标签 ID 列表序列化为 JSON 保存。
     */
    public function setTagidsAttribute(mixed $value): void
    {
        $this->_toJson($value, 'tagids');
    }

    /**
     * 将微信用户资料原文反序列化为数组。
     */
    public function getRawPayloadAttribute(mixed $value): array
    {
        return $this->_toArray($value);
    }

    /**
     * 将微信用户资料原文序列化为 JSON 保存。
     */
    public function setRawPayloadAttribute(mixed $value): void
    {
        $this->_toJson($value, 'raw_payload');
    }
}
