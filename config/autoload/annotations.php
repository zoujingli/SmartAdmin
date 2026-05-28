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
    'scan' => [
        'paths' => [
            syspath('app'),
            // 只扫描运行时插件目录，避免 SDK/测试目录被 Hyperf 注解扫描反射。
            // 业务插件通过本地 Composer path 包和 Provider 装配；这里保留运行时目录兜底，确保命令、控制器和监听器可直接发现。
            syspath('plugin/Builder'),
            syspath('plugin/System/src'),
            syspath('plugin/WechatClient/src'),
        ],
        // 初始化注解收集器
        'collectors' => [],
        'ignore_annotations' => [
            'mixin', 'required',
        ],
        'class_map' => [],
    ],
];
