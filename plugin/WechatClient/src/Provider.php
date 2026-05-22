<?php

declare(strict_types=1);

namespace Plugin\WechatClient;

/**
 * WechatClient 插件服务提供器。
 *
 * 当前仅声明注解扫描目录，插件菜单和资源入口由 plugin.json 负责。
 */
final class Provider
{
    /**
     * 注册租户侧微信插件注解扫描；菜单、模块和 view 由根目录 plugin.json 统一声明。
     *
     * @return array<string,mixed>
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
