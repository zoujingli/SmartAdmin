<?php

declare(strict_types=1);

namespace Plugin\WechatClient\Model;

use Hyperf\Database\Model\SoftDeletes;
use Library\CoreModel;

/**
 * @property int $id 主键ID
 * @property int $tenant_id 租户ID
 * @property int $account_id 接口账号ID
 * @property string $rule_type 规则类型
 * @property string $keyword 关键词
 * @property string $match_mode 匹配模式
 * @property string $reply_type 回复类型
 * @property int $delay_seconds 订阅延迟发送秒数
 * @property int $status 状态(1启用,0禁用)
 * @property int $sort 排序
 * @property int $created_by 创建者
 * @property int $updated_by 更新者
 * @property \Carbon\Carbon $created_at 创建时间
 * @property \Carbon\Carbon $updated_at 更新时间
 * @property string $deleted_at 删除时间
 * @property array $reply_content 回复内容 JSON
 */
final class WechatClientReplyRule extends CoreModel
{
    use SoftDeletes;

    /**
     * 微信模块模型显式声明表名，避免类名简化后与数据库命名规则产生偏差。
     */
    protected ?string $table = 'wechat_client_reply_rule';

    protected array $fillable = ['id', 'tenant_id', 'account_id', 'rule_type', 'keyword', 'match_mode', 'reply_type', 'reply_content', 'delay_seconds', 'status', 'sort', 'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at'];

    protected array $casts = ['id' => 'integer', 'tenant_id' => 'integer', 'account_id' => 'integer', 'delay_seconds' => 'integer', 'status' => 'integer', 'sort' => 'integer', 'created_by' => 'integer', 'updated_by' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];

    /**
     * 将回复内容反序列化为数组。
     */
    public function getReplyContentAttribute(mixed $value): array
    {
        return $this->_toArray($value);
    }

    /**
     * 将回复内容序列化为 JSON 保存。
     */
    public function setReplyContentAttribute(mixed $value): void
    {
        $this->_toJson($value, 'reply_content');
    }
}
