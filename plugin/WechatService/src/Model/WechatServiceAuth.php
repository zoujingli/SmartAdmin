<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Plugin\WechatService\Model;

use Carbon\Carbon;
use Hyperf\Database\Model\SoftDeletes;
use Library\Constants\Status;
use Library\CoreModel;

/**
 * @property int $id 主键ID
 * @property int $tenant_id 归属租户ID
 * @property string $authorizer_appid 授权账号 AppID
 * @property string $nick_name 授权账号昵称
 * @property string $account_type 账号类型
 * @property int $service_type 服务类型
 * @property int $verify_type 认证类型
 * @property string $principal_name 主体名称
 * @property string $qrcode_url 二维码地址
 * @property string $authorizer_access_token 授权账号 AccessToken 密文
 * @property string $authorizer_refresh_token 授权账号 RefreshToken 密文
 * @property int $expires_at 授权账号 Token 过期时间
 * @property int $total 网关调用次数
 * @property int $status 状态(1启用,0禁用)
 * @property string $auth_time 授权时间
 * @property int $created_by 创建者
 * @property int $updated_by 更新者
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 * @property string $deleted_at 删除时间
 * @property array $permissions 授权能力 JSON
 * @property array $raw_payload 官方原始数据 JSON
 */
final class WechatServiceAuth extends CoreModel
{
    use SoftDeletes;

    /**
     * 微信模块模型显式声明表名，避免类名简化后与数据库命名规则产生偏差。
     */
    protected ?string $table = 'wechat_service_auth';

    protected array $fillable = ['id', 'tenant_id', 'authorizer_appid', 'nick_name', 'account_type', 'service_type', 'verify_type', 'principal_name', 'qrcode_url', 'authorizer_access_token', 'authorizer_refresh_token', 'expires_at', 'permissions', 'raw_payload', 'total', 'status', 'auth_time', 'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at'];

    protected array $casts = ['id' => 'integer', 'tenant_id' => 'integer', 'service_type' => 'integer', 'verify_type' => 'integer', 'expires_at' => 'integer', 'total' => 'integer', 'status' => 'integer', 'created_by' => 'integer', 'updated_by' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];

    protected array $hidden = ['authorizer_access_token', 'authorizer_refresh_token', 'deleted_at'];

    protected array $logRules = [
        'name' => '微信授权账号',
        'title' => 'nick_name',
        'ignore' => ['authorizer_access_token', 'authorizer_refresh_token', 'raw_payload', 'permissions'],
        'fields' => [
            'tenant_id' => '租户ID',
            'authorizer_appid' => '授权 AppID',
            'nick_name' => '账号昵称',
            'account_type' => '账号类型',
            'status' => ['name' => '状态', 'values' => [Status::DISABLED => '禁用', Status::ENABLED => '启用']],
        ],
    ];

    public function getPermissionsAttribute(mixed $value): array
    {
        return $this->_toArray($value);
    }

    public function setPermissionsAttribute(mixed $value): void
    {
        $this->_toJson($value, 'permissions');
    }

    public function getRawPayloadAttribute(mixed $value): array
    {
        return $this->_toArray($value);
    }

    public function setRawPayloadAttribute(mixed $value): void
    {
        $this->_toJson($value, 'raw_payload');
    }
}
