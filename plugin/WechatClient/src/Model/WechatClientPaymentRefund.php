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
 * @property int $merchant_id 支付商户ID
 * @property int $order_id 订单ID
 * @property string $out_trade_no 商户订单号
 * @property string $out_refund_no 商户退款号
 * @property string $refund_id 微信退款单号
 * @property int $amount_total 订单金额(分)
 * @property int $amount_refund 退款金额(分)
 * @property string $refund_status 退款状态
 * @property string $reason 退款原因
 * @property int $status 状态(1启用,0禁用)
 * @property int $created_by 创建者
 * @property int $updated_by 更新者
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 * @property string $deleted_at 删除时间
 * @property string $order_no 业务订单号
 * @property string $notify_url 退款通知地址
 * @property string $refunded_at 退款成功时间
 * @property string $fail_reason 退款失败原因
 * @property array $raw_payload 官方原始数据 JSON
 */
final class WechatClientPaymentRefund extends CoreModel
{
    use SoftDeletes;

    /**
     * 微信模块模型显式声明表名，避免类名简化后与数据库命名规则产生偏差。
     */
    protected ?string $table = 'wechat_client_payment_refund';

    protected array $fillable = ['id', 'tenant_id', 'merchant_id', 'order_id', 'out_trade_no', 'out_refund_no', 'refund_id', 'amount_total', 'amount_refund', 'refund_status', 'raw_payload', 'reason', 'status', 'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at', 'order_no', 'notify_url', 'refunded_at', 'fail_reason'];

    protected array $casts = ['id' => 'integer', 'tenant_id' => 'integer', 'merchant_id' => 'integer', 'order_id' => 'integer', 'amount_total' => 'integer', 'amount_refund' => 'integer', 'status' => 'integer', 'created_by' => 'integer', 'updated_by' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];

    /**
     * 将微信请求、响应和通知原文统一反序列化为数组。
     */
    public function getRawPayloadAttribute(mixed $value): array
    {
        return $this->_toArray($value);
    }

    /**
     * 将微信请求、响应和通知原文统一序列化为 JSON 保存。
     */
    public function setRawPayloadAttribute(mixed $value): void
    {
        $this->_toJson($value, 'raw_payload');
    }
}
