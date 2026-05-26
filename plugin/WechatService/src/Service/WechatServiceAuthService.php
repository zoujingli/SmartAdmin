<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Plugin\WechatService\Service;

use Library\Constants\Status;
use Library\Constants\System;
use Library\CoreService;
use Library\Exception\ErrorResponseException;
use Plugin\WechatService\Mapper\WechatServiceAuthMapper;
use Plugin\WechatService\Model\WechatServiceAuth;
use Plugin\WechatService\Support\Secret;
use We\Contract\StoreTokenInterface;
use We\Platform\Wechat\ServiceClient as WechatServiceSdkClient;

final class WechatServiceAuthService extends CoreService implements StoreTokenInterface
{
    public function __construct(
        protected WechatServiceAuthMapper $mapper,
        private readonly WechatServiceConfigService $configs,
    ) {}

    public function refreshToken(string $authorizerAppid): string
    {
        $authorizer = $this->mapper->findByAppid($authorizerAppid);
        if (!$authorizer instanceof WechatServiceAuth || !Status::isEnabled((int)$authorizer->status)) {
            throw new ErrorResponseException('授权账号不可用');
        }

        return Secret::decrypt((string)$authorizer->authorizer_refresh_token);
    }

    public function saveAuthorizerToken(string $authorizerAppid, array $payload): void
    {
        $authorizer = $this->mapper->findByAppid($authorizerAppid);
        if (!$authorizer instanceof WechatServiceAuth || !Status::isEnabled((int)$authorizer->status)) {
            throw new ErrorResponseException('授权账号不可用');
        }

        // Token 刷新通常发生在开放平台回调或 JSON-RPC 场景，没有后台租户登录态；
        // 必须按 AppID 跨租户更新，否则会被 CoreModel 的 tenant_id 全局范围挡住。
        $updated = $this->mapper->updateByAppid($authorizerAppid, [
            'authorizer_access_token' => Secret::encrypt((string)($payload['authorizer_access_token'] ?? '')),
            'authorizer_refresh_token' => Secret::encrypt((string)($payload['authorizer_refresh_token'] ?? $this->refreshToken($authorizerAppid))),
            'expires_at' => time() + max(1, (int)($payload['expires_in'] ?? 7200) - 300),
        ]);
        if (!$updated) {
            throw new ErrorResponseException('授权账号保存失败');
        }
    }

    public function findByAppid(string $appid): ?WechatServiceAuth
    {
        return $this->mapper->findByAppid($appid);
    }

    public function incrementTotal(string $appid): void
    {
        $this->mapper->incrementTotal($appid);
    }

    /**
     * 授权回调后保存官方返回的授权账号资料。
     *
     * @param array<string,mixed> $payload
     */
    public function saveFromQueryAuth(array $payload, int $tenantId): WechatServiceAuth
    {
        if ($tenantId <= 0) {
            throw new ErrorResponseException('授权绑定租户无效');
        }
        // 微信开放平台授权回调没有后台登录态，必须先恢复租户上下文；否则模型保存监听器会把 tenant_id 回落为平台租户 0。
        System::setTenantId($tenantId);

        $auth = is_array($payload['authorization_info'] ?? null) ? $payload['authorization_info'] : [];
        $appid = (string)($auth['authorizer_appid'] ?? '');
        if ($appid === '') {
            throw new ErrorResponseException('授权返回缺少 authorizer_appid');
        }

        $componentToken = $this->configs->componentAccessToken();
        $info = $componentToken !== '' ? $this->serviceClient()->authorizerInfo($componentToken, $appid) : [];
        $authorizerInfo = is_array($info['authorizer_info'] ?? null) ? $info['authorizer_info'] : [];
        $serviceType = is_array($authorizerInfo['service_type_info'] ?? null) ? (int)($authorizerInfo['service_type_info']['id'] ?? 0) : 0;
        $verifyType = is_array($authorizerInfo['verify_type_info'] ?? null) ? (int)($authorizerInfo['verify_type_info']['id'] ?? 0) : 0;

        $data = [
            'tenant_id' => $tenantId,
            'authorizer_appid' => $appid,
            'nick_name' => (string)($authorizerInfo['nick_name'] ?? $appid),
            'account_type' => $this->resolveAccountType($authorizerInfo, $serviceType),
            'service_type' => $serviceType,
            'verify_type' => $verifyType,
            'principal_name' => (string)($authorizerInfo['principal_name'] ?? ''),
            'qrcode_url' => (string)($authorizerInfo['qrcode_url'] ?? ''),
            'authorizer_access_token' => Secret::encrypt((string)($auth['authorizer_access_token'] ?? '')),
            'authorizer_refresh_token' => Secret::encrypt((string)($auth['authorizer_refresh_token'] ?? '')),
            'expires_at' => time() + max(1, (int)($auth['expires_in'] ?? 7200) - 300),
            'permissions' => $auth['func_info'] ?? [],
            'raw_payload' => $payload,
            'status' => Status::ENABLED,
            'auth_time' => date('Y-m-d H:i:s'),
        ];

        $exists = $this->mapper->findAnyByAppid($appid);
        if ($exists instanceof WechatServiceAuth) {
            if (method_exists($exists, 'trashed') && $exists->trashed()) {
                // 授权账号 AppID 有唯一索引；用户删除后重新授权应恢复原记录，不能因软删除残留触发数据库唯一冲突。
                $exists->restore();
            }
            $exists->update($data);
            $updated = $this->mapper->findByAppid($appid);
            if (!$updated instanceof WechatServiceAuth) {
                throw new ErrorResponseException('授权账号保存失败');
            }

            return $updated;
        }

        /* @var WechatServiceAuth $created */
        return $this->mapper->create($data);
    }

    public function changeStatusByAppid(string $appid, int $status): bool
    {
        $authorizer = $this->mapper->findByAppid($appid);
        if (!$authorizer instanceof WechatServiceAuth) {
            throw new ErrorResponseException('授权账号不存在');
        }

        // AppID 状态变更可能由取消授权事件触发，不能要求当前协程处于该授权账号租户上下文。
        return $this->mapper->updateByAppid($appid, ['status' => $status]);
    }

    public function sync(int $id): WechatServiceAuth
    {
        $authorizer = $this->mapper->read($id);
        if (!$authorizer instanceof WechatServiceAuth) {
            throw new ErrorResponseException('授权账号不存在');
        }

        $componentToken = $this->configs->componentAccessToken();
        $info = $this->serviceClient()->authorizerInfo($componentToken, (string)$authorizer->authorizer_appid);
        $authorizerInfo = is_array($info['authorizer_info'] ?? null) ? $info['authorizer_info'] : [];
        if ($authorizerInfo === []) {
            throw new ErrorResponseException('授权账号资料同步失败');
        }

        $serviceType = is_array($authorizerInfo['service_type_info'] ?? null) ? (int)($authorizerInfo['service_type_info']['id'] ?? 0) : 0;
        $verifyType = is_array($authorizerInfo['verify_type_info'] ?? null) ? (int)($authorizerInfo['verify_type_info']['id'] ?? 0) : 0;
        $this->mapper->update($authorizer, [
            'nick_name' => (string)($authorizerInfo['nick_name'] ?? $authorizer->nick_name),
            'account_type' => $this->resolveAccountType($authorizerInfo, $serviceType),
            'service_type' => $serviceType,
            'verify_type' => $verifyType,
            'principal_name' => (string)($authorizerInfo['principal_name'] ?? ''),
            'qrcode_url' => (string)($authorizerInfo['qrcode_url'] ?? ''),
            'raw_payload' => $info,
        ]);

        $updated = $this->mapper->read($id);
        if (!$updated instanceof WechatServiceAuth) {
            throw new ErrorResponseException('授权账号同步失败');
        }

        return $updated;
    }

    public function handleAuthCallback(string $authCode, int $tenantId): WechatServiceAuth
    {
        if ($authCode === '') {
            throw new ErrorResponseException('授权码不能为空');
        }

        $componentToken = $this->configs->componentAccessToken();
        $payload = $this->serviceClient()->queryAuth($componentToken, $authCode);

        return $this->saveFromQueryAuth($payload, $tenantId);
    }

    protected function filterData(array &$data, array $exists = []): array
    {
        $rules = [
            'tenant_id.integer' => '租户 ID 必须为数字',
            'tenant_id.min:0' => '租户 ID 不能小于 0',
            'authorizer_appid.filled' => '授权 AppID 不能为空',
            'authorizer_appid.max:64' => '授权 AppID 最多 64 位',
            'nick_name.max:120' => '账号昵称最多 120 位',
            'account_type.max:30' => '账号类型最多 30 位',
            'status.integer' => '状态值必须为数字',
            'status.in:1,0' => '状态值错误',
        ];
        if ($exists === []) {
            $rules['tenant_id.default'] = tenant_id();
            $rules['authorizer_appid.required'] = '授权 AppID 不能为空';
            $rules['status.default'] = Status::ENABLED;
        }

        $data = _vali($rules, $data);
        foreach (['tenant_id', 'service_type', 'verify_type', 'expires_at', 'total', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = (int)$data[$field];
            }
        }

        return $data;
    }

    /**
     * 识别授权账号类型。
     *
     * 微信开放平台的小程序授权资料会带 MiniProgramInfo；不能只依赖 service_type_info，
     * 否则小程序会被错误展示为公众号并影响后续能力判断。
     *
     * @param array<string,mixed> $authorizerInfo
     */
    private function resolveAccountType(array $authorizerInfo, int $serviceType): string
    {
        if (is_array($authorizerInfo['MiniProgramInfo'] ?? null)) {
            return 'mini_program';
        }

        return $serviceType === 0 ? 'subscription' : 'official_account';
    }

    private function serviceClient(): WechatServiceSdkClient
    {
        return $this->configs->openClient()->wechatService($this->configs->sdkConfig());
    }
}
