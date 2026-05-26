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
    'generator' => [
        'amqp' => [
            'consumer' => [
                'namespace' => 'App\Amqp\Consumer',
            ],
            'producer' => [
                'namespace' => 'App\Amqp\Producer',
            ],
        ],
        'aspect' => [
            'namespace' => 'App\Aspect',
        ],
        'command' => [
            'namespace' => 'App\Command',
        ],
        'controller' => [
            'namespace' => 'App\Controller',
        ],
        'job' => [
            'namespace' => 'App\Job',
        ],
        'listener' => [
            'namespace' => 'App\Listener',
        ],
        'middleware' => [
            'namespace' => 'App\Middleware',
        ],
        'Process' => [
            'namespace' => 'App\Processes',
        ],
    ],
];
