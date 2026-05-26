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

use Library\CoreService;
use Plugin\WechatService\Mapper\WechatServiceLoggerMapper;

final class WechatServiceLoggerService extends CoreService
{
    public function __construct(
        protected WechatServiceLoggerMapper $mapper
    ) {}

    /**
     * 公共回调日志只记录结构化摘要，避免把密文、Token 或证书类内容写入业务日志。
     *
     * @param array<string,mixed> $payload
     */
    public function record(string $event, string $appid, array $payload, bool $success = true, string $message = ''): void
    {
        $this->mapper->create([
            'event' => $event,
            'appid' => $appid,
            'payload' => $payload,
            'status' => $success ? 1 : 0,
            'message' => mb_substr($message, 0, 500),
        ]);
    }
}
