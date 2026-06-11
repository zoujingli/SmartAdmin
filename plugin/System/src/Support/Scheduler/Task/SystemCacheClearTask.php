<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace System\Support\Scheduler\Task;

use Library\Events\Processor\AuthProcessor;
use Psr\SimpleCache\CacheInterface;
use System\Annotation\ScheduledTask;
use System\Contract\ScheduledTaskHandlerInterface;
use System\Service\AuthCacheService;
use System\Service\OnlineUserService;
use System\Service\UserService;
use System\Support\Scheduler\ScheduledTaskContext;

#[ScheduledTask(
    code: 'system.cache.clear',
    name: '清理系统缓存',
    description: '清理权限、用户快照、在线索引和通用缓存。',
    group: 'system',
    timeout: 600
)]
final class SystemCacheClearTask implements ScheduledTaskHandlerInterface
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly AuthCacheService $authCache,
        private readonly UserService $users,
        private readonly OnlineUserService $onlineUsers,
    ) {}

    public function handle(ScheduledTaskContext $context): array
    {
        $deep = (bool)($context->params['deep'] ?? false);
        $this->authCache->bumpGlobalVersion();
        $this->users->clearAllUserListSnapshots();
        $this->onlineUsers->clearAll();
        AuthProcessor::clearCache();
        $cacheCleared = $deep ? $this->cache->clear() : null;

        return [
            'deep' => $deep,
            'cache_cleared' => $cacheCleared,
            'items' => [
                'permission_global_version',
                'user_list_snapshot_global_version',
                'online_user_index',
                'auth_annotation_runtime_cache',
            ],
        ];
    }
}
