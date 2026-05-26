<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace System\Service;

final class UploadSessionStore
{
    private const CACHE_PREFIX = 'upload_session:';

    /**
     * 写入上传会话。
     *
     * @param array<string, mixed> $session
     */
    public function put(array $session): void
    {
        $sessionId = (string)($session['session_id'] ?? '');
        if ($sessionId === '') {
            return;
        }

        _cache($this->key($sessionId), $session, $this->ttl($session));
    }

    /**
     * 读取上传会话。
     *
     * @return null|array<string, mixed>
     */
    public function get(string $sessionId): ?array
    {
        $session = _cache($this->key($sessionId), '');
        return is_array($session) ? $session : null;
    }

    /**
     * 删除上传会话。
     */
    public function delete(string $sessionId): void
    {
        _cache($this->key($sessionId), null);
    }

    /**
     * @param array<string, mixed> $session
     */
    private function ttl(array $session): int
    {
        $expiresAt = (int)($session['expires_at'] ?? 0);
        return max(60, $expiresAt - time());
    }

    /**
     * 生成会话缓存键。
     */
    private function key(string $sessionId): string
    {
        return self::CACHE_PREFIX . $sessionId;
    }
}
