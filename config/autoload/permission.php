<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://github.com/zoujingli/SmartAdmin/blob/master/readme.md
 */
use function Hyperf\Support\env;

return [
    // 用户权限集合缓存时间（秒）
    'cache_ttl' => (int)env('PERMISSION_CACHE_TTL', 600),
];
