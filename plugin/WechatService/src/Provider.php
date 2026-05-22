<?php

declare(strict_types=1);

namespace Plugin\WechatService;

final class Provider
{
    /**
     * 注册微信开放平台插件的注解扫描；菜单、模块和 view 由根目录 plugin.json 统一声明。
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
