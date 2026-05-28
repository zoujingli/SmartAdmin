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
 * 公众号关注事件。
 *
 * 微信官方 subscribe 推送同步本地粉丝状态后派发，业务模块可监听该事件发放权益或记录来源参数。
 */
final class WechatClientUserSubscribed
{
    /**
     * @param array<string,mixed> $payload 微信公众号官方推送原始数据
     */
    public function __construct(
        public readonly WechatClientAccount $account,
        public readonly WechatClientUser $user,
        public readonly array $payload = [],
        public readonly string $eventKey = '',
        public readonly string $ticket = '',
    ) {}
}
