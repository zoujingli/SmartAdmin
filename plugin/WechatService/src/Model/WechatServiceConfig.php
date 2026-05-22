<?php

declare(strict_types=1);

namespace Plugin\WechatService\Model;

use Hyperf\Database\Model\SoftDeletes;
use Library\Constants\Status;
use Library\CoreModel;

/**
 * @property int $id 主键ID
 * @property string $name 配置名称
 * @property string $component_appid 第三方平台 AppID
 * @property string $component_appsecret 第三方平台 AppSecret 密文
 * @property string $component_token 消息校验 Token 密文
 * @property string $component_encodingaeskey 消息加解密 Key 密文
 * @property string $component_verify_ticket 开放平台推送 Ticket 密文
 * @property string $component_access_token 第三方平台 AccessToken 密文
 * @property int $component_expires_at 第三方平台 Token 过期时间
 * @property int $status 状态(1启用,0禁用)
 * @property string $remark 备注
 * @property int $created_by 创建者
 * @property int $updated_by 更新者
 * @property \Carbon\Carbon $created_at 创建时间
 * @property \Carbon\Carbon $updated_at 更新时间
 * @property string $deleted_at 删除时间
 */
final class WechatServiceConfig extends CoreModel
{
    use SoftDeletes;

    /**
     * 微信模块模型显式声明表名，避免类名简化后与数据库命名规则产生偏差。
     */
    protected ?string $table = 'wechat_service_config';

    protected array $fillable = ['id', 'name', 'component_appid', 'component_appsecret', 'component_token', 'component_encodingaeskey', 'component_verify_ticket', 'component_access_token', 'component_expires_at', 'status', 'remark', 'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at'];

    protected array $casts = ['id' => 'integer', 'component_expires_at' => 'integer', 'status' => 'integer', 'created_by' => 'integer', 'updated_by' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];

    protected array $hidden = [
        'component_appsecret',
        'component_token',
        'component_encodingaeskey',
        'component_verify_ticket',
        'component_access_token',
        'deleted_at',
    ];

    protected array $logRules = [
        'name' => '开放平台配置',
        'title' => 'name',
        'ignore' => ['component_appsecret', 'component_token', 'component_encodingaeskey', 'component_verify_ticket', 'component_access_token'],
        'fields' => [
            'name' => '配置名称',
            'component_appid' => '第三方平台 AppID',
            'status' => ['name' => '状态', 'values' => [Status::DISABLED => '禁用', Status::ENABLED => '启用']],
            'remark' => '备注',
        ],
    ];
}
