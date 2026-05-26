<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace System;

use Library\Interfaces\NodeNameResolverInterface;
use Library\Interfaces\OperateLogWriterInterface;
use System\Service\LogsActionService;
use System\Service\MenuService;

final class Provider
{
    /**
     * 注册 System 插件配置。
     *
     * 包含依赖绑定、公共能力元信息与注解扫描路径；菜单和模块目录统一由 plugin.json 声明。
     *
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        return [
            'listeners' => [],
            'dependencies' => [
                NodeNameResolverInterface::class => MenuService::class,
                OperateLogWriterInterface::class => LogsActionService::class,
            ],
            'xadmin' => [
                'common_capabilities' => [
                    'auth' => ['name' => '认证鉴权', 'description' => 'JWT 登录、登录态校验、超级管理员短路控制。'],
                    'permission' => ['name' => '权限节点', 'description' => '@Auth 注解、system_node 注册表与角色节点授权统一收口。'],
                    'menu' => ['name' => '菜单路由', 'description' => '代码菜单基线、数据库菜单表与运行路由树保持同一口径。'],
                    'scope' => ['name' => '数据范围', 'description' => '通过 ScopeProcessor 统一注入用户和部门的数据访问范围。'],
                    'cache' => ['name' => '缓存切换', 'description' => '统一基于 Hyperf Cache，默认 file，可在 redis、memory 等驱动间切换；生产推荐 redis。'],
                    'audit' => ['name' => '审计日志', 'description' => '通过 Logger 注解记录登录、查询和关键写操作。'],
                    'bootstrap' => ['name' => '重构基线', 'description' => '菜单、权限节点、角色绑定和管理员初始化由脚本统一同步。'],
                ],
            ],
            'annotations' => [
                'scan' => [
                    'paths' => [__DIR__],
                    'collectors' => [],
                ],
            ],
        ];
    }
}
