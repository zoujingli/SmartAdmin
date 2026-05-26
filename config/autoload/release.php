<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */
return [
    // 发布包必要数据表：未指定 --with-data 时只导出和恢复这些表的数据；结构快照始终包含当前数据库全部表。
    'backup_tables' => [
        'system_menu',
    ],

    // 不进入“必要数据”的运行表；优先级高于 backup_tables，--with-data 运行备份仍可完整导出运行数据。
    'ignore_tables' => [
        'system_logs_action',
        'system_logs_change',
    ],
];
