<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Plugin\WechatService\Support;

use Library\Exception\ErrorResponseException;
use Library\Helper\CoderHelper;

use function Hyperf\Config\config;

final class BindState
{
    public static function make(int $tenantId, int $ttl = 900): string
    {
        if ($tenantId <= 0) {
            throw new ErrorResponseException('授权绑定租户无效');
        }
        $time = time();
        $nonce = CoderHelper::genRandCode(16, 3);
        $payload = [
            'tenant_id' => $tenantId,
            'time' => $time,
            'ttl' => $ttl,
            'nonce' => $nonce,
        ];
        $payload['sign'] = self::sign($tenantId, $time, $nonce, $ttl);

        return CoderHelper::ensafe64(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
    }

    public static function verify(string $state): int
    {
        try {
            $payload = json_decode(CoderHelper::desafe64($state), true);
        } catch (\Throwable) {
            $payload = null;
        }
        if (!is_array($payload)) {
            throw new ErrorResponseException('授权绑定状态无效');
        }

        $tenantId = (int)($payload['tenant_id'] ?? 0);
        $time = (int)($payload['time'] ?? 0);
        $ttl = (int)($payload['ttl'] ?? 900);
        $nonce = (string)($payload['nonce'] ?? '');
        $sign = (string)($payload['sign'] ?? '');
        if ($tenantId <= 0 || $time <= 0 || $nonce === '' || $sign === '') {
            throw new ErrorResponseException('授权绑定状态参数不完整');
        }
        if ($time + max(60, $ttl) < time()) {
            throw new ErrorResponseException('授权绑定状态已过期');
        }
        if (!hash_equals(self::sign($tenantId, $time, $nonce, $ttl), $sign)) {
            throw new ErrorResponseException('授权绑定状态签名无效');
        }

        return $tenantId;
    }

    private static function sign(int $tenantId, int $time, string $nonce, int $ttl): string
    {
        $key = (string)config('jwt.secret', 'smart_admin-wechat-state');

        return hash_hmac('sha256', implode('|', [$tenantId, $time, $nonce, $ttl]), $key);
    }
}
