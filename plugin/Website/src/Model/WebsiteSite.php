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

use Hyperf\Database\Model\Relations\HasMany;
use Hyperf\Database\Model\SoftDeletes;
use Library\Constants\Status;
use Library\CoreModel;
use Plugin\Website\Support\WebsiteData;

/**
 * 官网站点模型。
 *
 * @property int $id 主键ID
 * @property int $tenant_id 租户ID
 * @property string $code 站点编码
 * @property string $name 站点名称
 * @property string $domain 主域名
 * @property array $aliases 备用域名 JSON
 * @property string $logo Logo 地址
 * @property string $favicon Favicon 地址
 * @property array $seo SEO 配置 JSON
 * @property array $contact 联系方式 JSON
 * @property array $config 站点扩展配置 JSON
 * @property int $status 状态(1启用,0禁用)
 */
final class WebsiteSite extends CoreModel
{
    use SoftDeletes;

    protected ?string $table = 'website_site';

    protected array $hidden = ['deleted_at'];

    protected array $fillable = ['id', 'tenant_id', 'code', 'name', 'domain', 'aliases', 'logo', 'favicon', 'seo', 'contact', 'config', 'status', 'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at'];

    protected array $casts = ['id' => 'integer', 'tenant_id' => 'integer', 'status' => 'integer', 'created_by' => 'integer', 'updated_by' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];

    protected array $logRules = [
        'name' => '官网站点',
        'title' => 'name',
        'fields' => [
            'code' => '站点编码',
            'name' => '站点名称',
            'domain' => '主域名',
            'logo' => 'Logo',
            'favicon' => 'Favicon',
            'status' => ['name' => '状态', 'values' => [Status::DISABLED => '禁用', Status::ENABLED => '启用']],
        ],
    ];

    public function channels(): HasMany
    {
        return $this->hasMany(WebsiteChannel::class, 'site_id', 'id');
    }

    public function getAliasesAttribute(mixed $value): array
    {
        return WebsiteData::stringList($value);
    }

    public function setAliasesAttribute(mixed $value): void
    {
        $this->attributes['aliases'] = WebsiteData::encodeList($value);
    }

    public function getSeoAttribute(mixed $value): array
    {
        return WebsiteData::object($value);
    }

    public function setSeoAttribute(mixed $value): void
    {
        $this->attributes['seo'] = WebsiteData::encodeObject($value);
    }

    public function getContactAttribute(mixed $value): array
    {
        return WebsiteData::object($value);
    }

    public function setContactAttribute(mixed $value): void
    {
        $this->attributes['contact'] = WebsiteData::encodeObject($value);
    }

    public function getConfigAttribute(mixed $value): array
    {
        return WebsiteData::object($value);
    }

    public function setConfigAttribute(mixed $value): void
    {
        $this->attributes['config'] = WebsiteData::encodeObject($value);
    }
}
