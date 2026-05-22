<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://github.com/zoujingli/SmartAdmin/blob/master/readme.md
 */

return [
    // 发布包强制接管的数据表：打包时导出，升级前备份，恢复时清空后直接替换。
    'backup_tables' => [
        'system_menu',
    ],

    // 发布系统完全不维护的表：不进入结构快照、不参与 DBAL diff、不备份也不恢复。
    'ignore_tables' => [
        'system_logs_action',
        'system_logs_change',
    ],
];
