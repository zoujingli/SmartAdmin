<?php

declare(strict_types=1);

namespace Plugin\WechatClient\Model;

use Hyperf\Database\Model\SoftDeletes;
use Library\CoreModel;

/**
 * @property int $id 主键ID
 * @property int $tenant_id 租户ID
 * @property int $account_id 接口账号ID
 * @property string $appid 支付 AppID
 * @property string $mch_id 商户号
 * @property string $name 商户名称
 * @property string $api_v3_key APIv3 Key 密文
 * @property string $merchant_serial 商户证书序列号密文
 * @property string $merchant_private_key 商户私钥密文
 * @property string $platform_public_key 微信支付平台公钥密文
 * @property string $platform_serial 平台证书序列号密文
 * @property int $status 状态(1启用,0禁用)
 * @property int $created_by 创建者
 * @property int $updated_by 更新者
 * @property \Carbon\Carbon $created_at 创建时间
 * @property \Carbon\Carbon $updated_at 更新时间
 * @property string $deleted_at 删除时间
 */
final class WechatClientPaymentMerchant extends CoreModel
{
    use SoftDeletes;

    /**
     * 微信模块模型显式声明表名，避免类名简化后与数据库命名规则产生偏差。
     */
    protected ?string $table = 'wechat_client_payment_merchant';

    protected array $fillable = ['id', 'tenant_id', 'account_id', 'appid', 'mch_id', 'name', 'api_v3_key', 'merchant_serial', 'merchant_private_key', 'platform_public_key', 'platform_serial', 'status', 'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at'];

    protected array $casts = ['id' => 'integer', 'tenant_id' => 'integer', 'account_id' => 'integer', 'status' => 'integer', 'created_by' => 'integer', 'updated_by' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];

    protected array $hidden = ['api_v3_key', 'merchant_serial', 'merchant_private_key', 'platform_public_key', 'platform_serial', 'deleted_at'];

    protected array $logRules = [
        'name' => '微信支付商户',
        'title' => 'name',
        'ignore' => ['api_v3_key', 'merchant_serial', 'merchant_private_key', 'platform_public_key', 'platform_serial'],
        'fields' => [
            'appid' => '支付 AppID',
            'mch_id' => '商户号',
            'name' => '商户名称',
            'status' => ['name' => '状态', 'values' => [0 => '禁用', 1 => '启用']],
        ],
    ];

}
