<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Plugin\Website\Service;

use Library\Constants\Status;
use Library\CoreService;
use Plugin\Website\Mapper\WebsiteSiteMapper;
use Plugin\Website\Support\WebsiteData;

/**
 * 官网站点服务。
 */
final class WebsiteSiteService extends CoreService
{
    public function __construct(
        protected WebsiteSiteMapper $mapper
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function options(array $params = []): array
    {
        return $this->mapper->options($params);
    }

    protected function filterData(array &$data, array $exists = []): array
    {
        // 官网站点租户归属不接受请求体输入；创建取当前登录租户，编辑保持原归属不变。
        unset($data['tenant_id']);
        foreach (['code', 'name', 'domain', 'logo', 'favicon'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }
        foreach (['code', 'domain'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = strtolower((string)$data[$field]);
            }
        }

        $rules = [
            'tenant_id.integer' => '租户 ID 必须为数字',
            'tenant_id.min:0' => '租户 ID 不能小于 0',
            'code.filled' => '站点编码不能为空',
            'code.max:60' => '站点编码最多 60 位',
            'name.filled' => '站点名称不能为空',
            'name.max:120' => '站点名称最多 120 位',
            'domain.filled' => '主域名不能为空',
            'domain.max:120' => '主域名最多 120 位',
            'aliases.nullable' => '备用域名格式错误',
            'logo.max:500' => 'Logo 地址最多 500 位',
            'favicon.max:500' => 'Favicon 地址最多 500 位',
            'seo.nullable' => 'SEO 配置格式错误',
            'contact.nullable' => '联系方式格式错误',
            'config.nullable' => '站点配置格式错误',
            'status.integer' => '状态必须为数字',
            'status.in:1,0' => '状态值错误',
        ];
        if ($exists === []) {
            $rules['tenant_id.default'] = tenant_id();
            $rules['code.required'] = '站点编码不能为空';
            $rules['name.required'] = '站点名称不能为空';
            $rules['domain.required'] = '主域名不能为空';
            $rules['aliases.default'] = [];
            $rules['seo.default'] = [];
            $rules['contact.default'] = [];
            $rules['config.default'] = [];
            $rules['status.default'] = Status::ENABLED;
        }

        $data = _vali($rules, $data);
        if ($exists === []) {
            // 后台页面不展示租户字段；创建时强制取当前登录租户，防止请求体指定 tenant_id 跨租户写入。
            $data['tenant_id'] = tenant_id();
        }
        foreach (['status', 'tenant_id'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = (int)$data[$field];
            }
        }
        if (array_key_exists('aliases', $data)) {
            $data['aliases'] = array_map('strtolower', WebsiteData::stringList($data['aliases']));
        }
        foreach (['seo', 'contact', 'config'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = WebsiteData::object($data[$field]);
            }
        }

        $this->ensureUniqueField('code', $data, $exists, '站点编码已存在');
        $this->ensureUniqueField('domain', $data, $exists, '主域名已存在');

        return $data;
    }
}
