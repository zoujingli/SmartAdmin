<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://github.com/zoujingli/SmartAdmin/blob/master/readme.md
 */
use Hyperf\Database\Commands\CommandCollector;
use Hyperf\Database\Commands\Migrations\InstallCommand;
use Hyperf\Database\Commands\Migrations\MigrateCommand;
use Hyperf\Database\Commands\Migrations\StatusCommand;

// Phar/SFX 发布包只保留前向迁移升级所需命令；不暴露 rollback/fresh/reset/refresh、seed、gen:* 等开发或高风险入口。
// Hyperf 3.2 的前向迁移命令名是 migrate（不是 migrate:run），生产执行时应先 status/pretend，再按需加 --force。
if (\Phar::running(false) !== '') {
    return [
        InstallCommand::class,
        MigrateCommand::class,
        StatusCommand::class,
    ];
}

// 注册 Hyperf 数据库相关控制台命令（migrate、gen:model、db:seed 等）。
// 插件在各自 Provider 中追加 xadmin:* 等命令。
return CommandCollector::getAllCommands();
