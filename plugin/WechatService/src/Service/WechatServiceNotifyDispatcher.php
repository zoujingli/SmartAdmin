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
use Plugin\WechatClient\Service\WechatClientAccountService;
use Plugin\WechatClient\Service\WechatClientReplyRuleService;
use Plugin\WechatClient\Service\WechatClientUserService;
use Plugin\WechatService\Mapper\WechatServiceAuthMapper;
use Plugin\WechatService\Model\WechatServiceAuth;

/**
 * 微信开放平台授权消息分发器。
 *
 * 负责把开放平台「消息与事件接收 URL」解密后的授权账号消息交给租户侧 WechatClient 处理，
 * 同时同步取消授权事件的本地状态。这里采用插件存在性检测，避免 WechatService
 * 在独立安装时强依赖租户侧公众号插件。
 */
final class WechatServiceNotifyDispatcher
{
    public function __construct(
        private readonly WechatServiceAuthMapper $authorizers,
    ) {}

    /**
     * 分发开放平台普通消息/事件。
     *
     * 返回值为未加密的被动回复 XML；上层控制器会根据原回调模式决定是否重新加密。
     *
     * @param array<string,mixed> $payload
     */
    public function dispatch(array $payload): ?string
    {
        $this->syncAuthorizerEvent($payload);
        if (!$this->isClientMessage($payload) || !$this->clientPluginAvailable()) {
            return null;
        }

        $appid = $this->payloadAppid($payload);
        if ($appid === '') {
            return null;
        }

        try {
            /** @var WechatClientAccountService $accounts */
            $accounts = _once(WechatClientAccountService::class);
            $account = $accounts->findByAppidForCallback($appid);
            // 开放平台回调没有后台登录态，必须按授权账号恢复租户上下文，后续粉丝、回复规则查询才会命中租户隔离。
            System::setTenantId((int)$account->tenant_id);

            /** @var WechatClientUserService $users */
            $users = _once(WechatClientUserService::class);
            /** @var WechatClientReplyRuleService $replies */
            $replies = _once(WechatClientReplyRuleService::class);

            $users->handleOfficialPush($account, $payload);

            return $replies->handleOfficialPush($account, $payload);
        } catch (\Throwable) {
            // 授权账号尚未在租户侧创建接口账号、Client 插件未安装或回复逻辑异常时，
            // 只保留开放平台回调日志，不阻断微信成功响应，避免微信反复重试造成消息风暴。
            return null;
        }
    }

    /**
     * 同步开放平台授权账号状态。
     *
     * unauthorized 必须立即禁用，避免后续 JSON-RPC 仍继续代调用；authorized/updateauthorized
     * 不在这里自动启用，真正的授权资料仍以授权回调和后台同步为准，避免覆盖管理员手动禁用状态。
     *
     * @param array<string,mixed> $payload
     */
    private function syncAuthorizerEvent(array $payload): void
    {
        $infoType = strtolower(trim((string)($payload['InfoType'] ?? '')));
        if ($infoType !== 'unauthorized') {
            return;
        }

        $appid = $this->payloadAppid($payload);
        if ($appid === '') {
            return;
        }

        $authorizer = $this->authorizers->findByAppid($appid);
        if (!$authorizer instanceof WechatServiceAuth) {
            // 授权事件可能早于租户侧保存流程或来自已删除记录；此时保留回调日志即可。
            return;
        }
        // 取消授权必须跨租户禁用本地账号；授权/更新授权不在这里自动启用，避免覆盖管理员手动禁用状态。
        $this->authorizers->updateByAppid($appid, ['status' => Status::DISABLED]);
    }

    /**
     * 仅把真实公众号/小程序消息分发给 WechatClient；开放平台自身授权通知不进入粉丝和自动回复链路。
     *
     * @param array<string,mixed> $payload
     */
    private function isClientMessage(array $payload): bool
    {
        return trim((string)($payload['MsgType'] ?? '')) !== ''
            && trim((string)($payload['FromUserName'] ?? '')) !== '';
    }

    private function clientPluginAvailable(): bool
    {
        return class_exists(WechatClientAccountService::class)
            && class_exists(WechatClientUserService::class)
            && class_exists(WechatClientReplyRuleService::class);
    }

    /**
     * 提取授权账号 AppID，覆盖开放平台授权通知和普通消息两类 XML 字段。
     *
     * @param array<string,mixed> $payload
     */
    private function payloadAppid(array $payload): string
    {
        foreach (['AuthorizerAppid', 'AppId', 'appid', 'ToUserName'] as $field) {
            $appid = trim((string)($payload[$field] ?? ''));
            if ($appid !== '') {
                return $appid;
            }
        }

        return '';
    }
}
