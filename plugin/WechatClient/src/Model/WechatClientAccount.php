<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Plugin\WechatClient\Model;

use Carbon\Carbon;
use Hyperf\Database\Model\SoftDeletes;
use Library\CoreModel;

/**
 * @property int $id 主键ID
 * @property int $tenant_id 租户ID
 * @property string $appid 微信 AppID
 * @property string $appsecret AppSecret 密文
 * @property string $name 账号名称
 * @property int $status 状态(1启用,0禁用)
 * @property string $token 消息 Token 密文
 * @property string $account_type 账号类型
 * @property int $service_mode 接入模式(0直连,1开放平台授权)
 * @property string $encodingaeskey 消息加解密 Key 密文
 * @property string $access_token AccessToken 密文
 * @property string $refresh_token RefreshToken 密文
 * @property int $expires_at Token 过期时间
 * @property int $created_by 创建者
 * @property int $updated_by 更新者
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 * @property string $deleted_at 删除时间
 * @property array $extra 扩展配置 JSON
 */
final class WechatClientAccount extends CoreModel
{
    use SoftDeletes;

    /**
     * 微信模块模型显式声明表名，避免类名简化后与数据库命名规则产生偏差。
     */
    protected ?string $table = 'wechat_client_account';

    protected array $fillable = ['id', 'tenant_id', 'appid', 'appsecret', 'name', 'status', 'token', 'account_type', 'service_mode', 'encodingaeskey', 'access_token', 'refresh_token', 'expires_at', 'extra', 'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at'];

    protected array $casts = ['id' => 'integer', 'tenant_id' => 'integer', 'status' => 'integer', 'service_mode' => 'integer', 'expires_at' => 'integer', 'created_by' => 'integer', 'updated_by' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];

    protected array $hidden = ['appsecret', 'token', 'encodingaeskey', 'access_token', 'refresh_token', 'deleted_at'];

    protected array $logRules = [
        'name' => '微信接口账号',
        'title' => 'name',
        'ignore' => ['appsecret', 'token', 'encodingaeskey', 'access_token', 'refresh_token', 'extra'],
        'fields' => [
            'appid' => '微信 AppID',
            'name' => '账号名称',
            'account_type' => '账号类型',
            'service_mode' => ['name' => '接入模式', 'values' => [0 => '直连', 1 => '开放平台']],
            'status' => ['name' => '状态', 'values' => [0 => '禁用', 1 => '启用']],
        ],
    ];

    /**
     * 输出扩展配置时对网关密钥脱敏，避免后台接口泄露密文。
     */
    public function getExtraAttribute(mixed $value): array
    {
        $extra = $this->_toArray($value);
        if (trim((string)($extra['gateway_client_secret'] ?? '')) !== '') {
            // 网关密钥只允许内部读取密文，模型输出给接口时统一脱敏。
            $extra['gateway_client_secret'] = '******';
        }

        return $extra;
    }

    /**
     * 将扩展配置统一序列化为 JSON 保存。
     */
    public function setExtraAttribute(mixed $value): void
    {
        $this->_toJson($value, 'extra');
    }
}
