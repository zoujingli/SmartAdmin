<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */
use Hyperf\Database\Commands\CommandCollector;
use Library\Constants\System;

// Phar/SFX 发布包不暴露 migrate 体系；正式安装与升级统一走 xadmin:release:install/restore 的发布恢复流程。
// migrate、rollback、fresh、reset、refresh、seed、gen:* 等数据库开发命令仅保留在源码/本地开发环境。
if (System::isPharMode()) {
    return [];
}

// 注册 Hyperf 数据库相关控制台命令（migrate、gen:model、db:seed 等）。
// 插件在各自 Provider 中追加 xadmin:* 等命令。
return CommandCollector::getAllCommands();
