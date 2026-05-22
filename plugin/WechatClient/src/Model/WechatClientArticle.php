<?php

declare(strict_types=1);

namespace Plugin\WechatClient\Model;

use Hyperf\Database\Model\SoftDeletes;
use Library\CoreModel;

/**
 * @property int $id 主键ID
 * @property int $tenant_id 租户ID
 * @property int $account_id 接口账号ID
 * @property string $title 文章标题
 * @property string $author 作者
 * @property string $thumb_media_id 封面 MediaID
 * @property string $thumb_url 封面地址
 * @property string $content 正文内容
 * @property string $digest 摘要
 * @property string $content_source_url 原文链接
 * @property string $draft_media_id 草稿 MediaID
 * @property string $publish_id 发布任务ID
 * @property string $publish_status 发布状态
 * @property int $status 状态(1启用,0禁用)
 * @property int $created_by 创建者
 * @property int $updated_by 更新者
 * @property \Carbon\Carbon $created_at 创建时间
 * @property \Carbon\Carbon $updated_at 更新时间
 * @property string $deleted_at 删除时间
 * @property array $raw_payload 官方原始数据 JSON
 */
final class WechatClientArticle extends CoreModel
{
    use SoftDeletes;

    /**
     * 微信模块模型显式声明表名，避免类名简化后与数据库命名规则产生偏差。
     */
    protected ?string $table = 'wechat_client_article';

    protected array $fillable = ['id', 'tenant_id', 'account_id', 'title', 'author', 'thumb_media_id', 'thumb_url', 'content', 'digest', 'content_source_url', 'draft_media_id', 'publish_id', 'publish_status', 'raw_payload', 'status', 'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at'];

    protected array $casts = ['id' => 'integer', 'tenant_id' => 'integer', 'account_id' => 'integer', 'status' => 'integer', 'created_by' => 'integer', 'updated_by' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];

    /**
     * 将微信接口原始数据反序列化为数组。
     */
    public function getRawPayloadAttribute(mixed $value): array
    {
        return $this->_toArray($value);
    }

    /**
     * 将微信接口原始数据序列化为 JSON 保存。
     */
    public function setRawPayloadAttribute(mixed $value): void
    {
        $this->_toJson($value, 'raw_payload');
    }
}
