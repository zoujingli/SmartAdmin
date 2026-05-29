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
        ],
        // 初始化注解收集器
        'collectors' => [],
        'ignore_annotations' => [
            'mixin', 'required',
        ],
        'class_map' => [],
    ],
];
