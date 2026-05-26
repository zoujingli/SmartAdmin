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
 * @property string $client_key 网关调用 Key
 * @property string $client_secret 网关调用 Secret 密文
 * @property string $name 凭据名称
 * @property int $total 调用次数
 * @property int $status 状态(1启用,0禁用)
 * @property string $remark 备注
 * @property int $created_by 创建者
 * @property int $updated_by 更新者
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 * @property string $deleted_at 删除时间
 * @property array $allowed_appids 允许调用的授权 AppID JSON
 */
final class WechatServiceGateway extends CoreModel
{
    use SoftDeletes;

    /**
     * 微信模块模型显式声明表名，避免类名简化后与数据库命名规则产生偏差。
     */
    protected ?string $table = 'wechat_service_gateway';

    protected array $fillable = ['id', 'client_key', 'client_secret', 'name', 'allowed_appids', 'total', 'status', 'remark', 'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at'];

    protected array $casts = ['id' => 'integer', 'total' => 'integer', 'status' => 'integer', 'created_by' => 'integer', 'updated_by' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];

    protected array $hidden = ['client_secret', 'deleted_at'];

    protected array $logRules = [
        'name' => '开放平台网关凭据',
        'title' => 'name',
        'ignore' => ['client_secret'],
        'fields' => [
            'client_key' => '调用 Key',
            'name' => '凭据名称',
            'status' => ['name' => '状态', 'values' => [Status::DISABLED => '禁用', Status::ENABLED => '启用']],
            'remark' => '备注',
        ],
    ];

    public function getAllowedAppidsAttribute(mixed $value): array
    {
        return $this->_toArray($value);
    }

    public function setAllowedAppidsAttribute(mixed $value): void
    {
        $this->_toJson($value, 'allowed_appids');
    }
}
