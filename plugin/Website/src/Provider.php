<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Plugin\Website;

/**
 * Website 插件服务提供器。
 *
 * 插件后端能力通过注解扫描接入；后台管理页面、菜单、迁移和打包边界继续由 plugin.json 统一承载。
 */
final class Provider
{
    /**
     * 注册 Website 插件注解扫描路径。
     *
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        return [
            'annotations' => [
                'scan' => [
                    'paths' => [__DIR__],
                    'collectors' => [],
                ],
            ],
        ];
    }
}
