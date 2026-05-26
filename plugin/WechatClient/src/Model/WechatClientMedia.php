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
 * @property int $account_id 接口账号ID
 * @property string $name 素材名称
 * @property string $url 素材地址
 * @property int $file_id 本地文件ID
 * @property string $file_url 本地或远程文件地址
 * @property string $media_id 微信素材 MediaID
 * @property string $media_type 素材类型
 * @property int $status 状态(1启用,0禁用)
 * @property int $created_by 创建者
 * @property int $updated_by 更新者
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 * @property string $deleted_at 删除时间
 * @property array $raw_payload 官方原始数据 JSON
 */
final class WechatClientMedia extends CoreModel
{
    use SoftDeletes;

    /**
     * 微信模块模型显式声明表名，避免类名简化后与数据库命名规则产生偏差。
     */
    protected ?string $table = 'wechat_client_media';

    protected array $fillable = ['id', 'tenant_id', 'account_id', 'name', 'url', 'file_id', 'file_url', 'media_id', 'media_type', 'raw_payload', 'status', 'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at'];

    protected array $casts = ['id' => 'integer', 'tenant_id' => 'integer', 'account_id' => 'integer', 'file_id' => 'integer', 'status' => 'integer', 'created_by' => 'integer', 'updated_by' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];

    /**
     * 将微信素材接口原始数据反序列化为数组。
     */
    public function getRawPayloadAttribute(mixed $value): array
    {
        return $this->_toArray($value);
    }

    /**
     * 将微信素材接口原始数据序列化为 JSON 保存。
     */
    public function setRawPayloadAttribute(mixed $value): void
    {
        $this->_toJson($value, 'raw_payload');
    }
}
