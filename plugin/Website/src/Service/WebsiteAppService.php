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

use Hyperf\Database\Model\Model;
use Library\Constants\Status;
use Library\CoreService;
use Library\Exception\ErrorResponseException;
use Plugin\Website\Mapper\WebsiteAppMapper;
use Plugin\Website\Model\WebsiteApp;
use Plugin\Website\Service\Concerns\WebsiteServiceHelpers;
use Plugin\Website\Support\Secret;
use Plugin\Website\Support\WebsiteData;
use Plugin\Website\Support\WebsiteOpenApiScope;

/**
 * 官网开放接口应用服务。
 */
final class WebsiteAppService extends CoreService
{
    use WebsiteServiceHelpers;

    public function __construct(
        protected WebsiteAppMapper $mapper
    ) {}

    /**
     * 创建应用时生成 app_id 和 app_key；app_key 明文只随本次响应返回，数据库仅保存密文。
     *
     * @return array<string, mixed>
     */
    public function createWithSecret(array $data): array
    {
        $plainKey = self::generateAppKey();
        $data['app_id'] = $this->generateAppId();
        $data['app_key'] = Secret::encrypt($plainKey);
        $app = $this->mapper->create($this->filterData($data));

        return $this->safeData($app, $plainKey);
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(int $id): array
    {
        $app = $this->mapper->read($id);
        if (!$app instanceof WebsiteApp) {
            throw new ErrorResponseException('接口应用不存在');
        }

        return $this->safeData($app);
    }

    /**
     * 重置密钥会立即替换旧 app_key；旧密钥随即失效，明文只在本次响应中返回。
     *
     * @return array<string, mixed>
     */
    public function resetKey(int $id): array
    {
        $app = $this->mapper->read($id);
        if (!$app instanceof WebsiteApp) {
            throw new ErrorResponseException('接口应用不存在');
        }

        $plainKey = self::generateAppKey();
        $app->update(['app_key' => Secret::encrypt($plainKey)]);

        return $this->safeData($app->refresh(), $plainKey);
    }

    /**
     * @return array<string, mixed>
     */
    public function safeData(Model $model, ?string $plainKey = null): array
    {
        if ($model instanceof WebsiteApp) {
            $model->loadMissing(['site' => fn ($query) => $query->select(['id', 'code', 'name'])]);
        }

        $data = $model->toArray();
        $data['app_key'] = $plainKey ?? Secret::mask((string)$model->getRawOriginal('app_key'));
        $data['scope_texts'] = array_map(WebsiteOpenApiScope::label(...), WebsiteData::stringList($data['scopes'] ?? []));

        return $data;
    }

    protected function filterData(array &$data, array $exists = []): array
    {
        $this->trimStringFields($data, ['name', 'app_id', 'last_used_ip', 'remark']);
        if ($exists !== []) {
            // AppID 和 AppKey 是第三方凭证身份，不允许普通编辑接口变更；重置密钥走专用接口。
            unset($data['app_id'], $data['app_key'], $data['tenant_id'], $data['last_used_at'], $data['last_used_ip']);
        }
        if (array_key_exists('app_id', $data)) {
            $data['app_id'] = strtolower((string)$data['app_id']);
        }

        $rules = [
            'site_id.integer' => '所属站点必须为数字',
            'site_id.min:1' => '请选择所属站点',
            'name.filled' => '应用名称不能为空',
            'name.max:120' => '应用名称最多 120 位',
            'app_id.max:80' => 'AppID 最多 80 位',
            'app_key.max:1000' => 'AppKey 密文过长',
            'scopes.nullable' => '接口权限格式错误',
            'ip_whitelist.nullable' => 'IP 白名单格式错误',
            'rate_limit.integer' => '每分钟限流必须为数字',
            'rate_limit.min:0' => '每分钟限流不能小于 0',
            'rate_limit.max:100000' => '每分钟限流不能超过 100000',
            'status.integer' => '状态必须为数字',
            'status.in:1,0' => '状态值错误',
            'remark.max:1000' => '备注最多 1000 位',
        ];
        if ($exists === []) {
            $rules['site_id.required'] = '请选择所属站点';
            $rules['name.required'] = '应用名称不能为空';
            $rules['app_id.required'] = 'AppID 不能为空';
            $rules['app_key.required'] = 'AppKey 不能为空';
            $rules['scopes.default'] = WebsiteOpenApiScope::defaultReadScopes();
            $rules['ip_whitelist.default'] = [];
            $rules['rate_limit.default'] = 60;
            $rules['status.default'] = Status::ENABLED;
        }

        $data = _vali($rules, $data);
        $this->normalizeIntFields($data, ['site_id', 'rate_limit', 'status']);
        if (array_key_exists('site_id', $data)) {
            $site = $this->ensureSite((int)$data['site_id']);
            $data['tenant_id'] = (int)$site->tenant_id;
        }
        if (array_key_exists('scopes', $data)) {
            $data['scopes'] = WebsiteOpenApiScope::normalize($data['scopes'], $exists === [] ? WebsiteOpenApiScope::defaultReadScopes() : null);
        }
        if (array_key_exists('ip_whitelist', $data)) {
            $data['ip_whitelist'] = $this->normalizeIpWhitelist($data['ip_whitelist']);
        }

        $this->ensureUniqueField('app_id', $data, $exists, 'AppID 已存在');

        return $data;
    }

    /**
     * @return array<int, string>
     */
    private function normalizeIpWhitelist(mixed $value): array
    {
        $items = WebsiteData::stringList($value);
        foreach ($items as $item) {
            if ($item === '*') {
                continue;
            }
            if (filter_var($item, FILTER_VALIDATE_IP)) {
                continue;
            }
            if (preg_match('/^([0-9]{1,3}(?:\.[0-9]{1,3}){3})\/(\d{1,2})$/', $item, $matches) === 1
                && filter_var($matches[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
                && (int)$matches[2] >= 0
                && (int)$matches[2] <= 32) {
                continue;
            }

            throw new ErrorResponseException(sprintf('IP 白名单格式错误：%s', $item));
        }

        return $items;
    }

    private function generateAppId(): string
    {
        do {
            $appId = 'wapp_' . bin2hex(random_bytes(10));
        } while ($this->mapper->existsByField('app_id', $appId));

        return $appId;
    }

    private static function generateAppKey(): string
    {
        return bin2hex(random_bytes(24));
    }
}
