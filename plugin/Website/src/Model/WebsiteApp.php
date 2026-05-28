<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Plugin\Website\Model;

use Hyperf\Database\Model\Relations\BelongsTo;
use Hyperf\Database\Model\SoftDeletes;
use Library\Constants\Status;
use Library\CoreModel;
use Plugin\Website\Support\WebsiteData;

/**
 * 官网开放接口应用模型。
 *
 * @property int $id 主键ID
 * @property int $tenant_id 租户ID
 * @property int $site_id 站点ID
 * @property string $name 应用名称
 * @property string $app_id AppID
 * @property string $app_key AppKey 密文
 * @property array $scopes 接口权限范围
 * @property array $ip_whitelist IP 白名单
 * @property int $rate_limit 每分钟限流
 * @property string $last_used_ip 最后调用 IP
 * @property int $status 状态
 */
final class WebsiteApp extends CoreModel
{
    use SoftDeletes;

    protected ?string $table = 'website_app';

    protected array $hidden = ['app_key', 'deleted_at'];

    protected array $fillable = ['id', 'tenant_id', 'site_id', 'name', 'app_id', 'app_key', 'scopes', 'ip_whitelist', 'rate_limit', 'last_used_at', 'last_used_ip', 'status', 'remark', 'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at'];

    protected array $casts = ['id' => 'integer', 'tenant_id' => 'integer', 'site_id' => 'integer', 'rate_limit' => 'integer', 'status' => 'integer', 'last_used_at' => 'datetime', 'created_by' => 'integer', 'updated_by' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];

    protected array $logRules = [
        'name' => '官网接口应用',
        'title' => 'name',
        'fields' => [
            'site_id' => '所属站点',
            'name' => '应用名称',
            'app_id' => 'AppID',
            'scopes' => '接口权限',
            'ip_whitelist' => 'IP白名单',
            'rate_limit' => ['name' => '每分钟限流', 'unit' => '次'],
            'status' => ['name' => '状态', 'values' => [Status::DISABLED => '禁用', Status::ENABLED => '启用']],
            'remark' => '备注',
        ],
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(WebsiteSite::class, 'site_id', 'id');
    }

    public function getScopesAttribute(mixed $value): array
    {
        return WebsiteData::stringList($value);
    }

    public function setScopesAttribute(mixed $value): void
    {
        $this->attributes['scopes'] = WebsiteData::encodeList($value);
    }

    public function getIpWhitelistAttribute(mixed $value): array
    {
        return WebsiteData::stringList($value);
    }

    public function setIpWhitelistAttribute(mixed $value): void
    {
        $this->attributes['ip_whitelist'] = WebsiteData::encodeList($value);
    }
}
