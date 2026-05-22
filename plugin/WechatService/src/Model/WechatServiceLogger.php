<?php

declare(strict_types=1);

namespace Plugin\WechatService\Model;

use Library\CoreModel;

/**
 * @property int $id 主键ID
 * @property string $event 回调事件
 * @property string $appid 相关 AppID
 * @property int $status 处理状态(1成功,0失败)
 * @property string $message 处理消息
 * @property \Carbon\Carbon $created_at 创建时间
 * @property \Carbon\Carbon $updated_at 更新时间
 * @property array $payload 回调数据 JSON
 */
final class WechatServiceLogger extends CoreModel
{
    /**
     * 微信模块模型显式声明表名，避免类名简化后与数据库命名规则产生偏差。
     */
    protected ?string $table = 'wechat_service_logger';

    protected array $fillable = ['id', 'event', 'appid', 'payload', 'status', 'message', 'created_at', 'updated_at'];

    protected array $casts = ['id' => 'integer', 'status' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];

    public function getPayloadAttribute(mixed $value): array
    {
        return $this->_toArray($value);
    }

    public function setPayloadAttribute(mixed $value): void
    {
        $this->_toJson($value, 'payload');
    }
}
