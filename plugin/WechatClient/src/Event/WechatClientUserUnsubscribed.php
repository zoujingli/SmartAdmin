<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Plugin\WechatClient\Event;

use Plugin\WechatClient\Model\WechatClientAccount;
use Plugin\WechatClient\Model\WechatClientUser;

/**
 * 公众号取消关注事件。
 *
 * 微信官方 unsubscribe 推送同步本地粉丝状态后派发，业务模块可监听该事件处理订阅关系。
 */
final class WechatClientUserUnsubscribed
{
    /**
     * @param array<string,mixed> $payload 微信公众号官方推送原始数据
     */
    public function __construct(
        public readonly WechatClientAccount $account,
        public readonly WechatClientUser $user,
        public readonly array $payload = [],
    ) {}
}
