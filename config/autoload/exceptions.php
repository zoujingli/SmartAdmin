<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */
use Hyperf\HttpServer\Exception\Handler\HttpExceptionHandler;
use Library\Exception\Handler\AppExceptionHandler;
use Library\Exception\Handler\ResponseExceptionHandler;

return [
    'handler' => [
        'http' => [
            ResponseExceptionHandler::class => 10,
            HttpExceptionHandler::class,
            AppExceptionHandler::class,
        ],
    ],
];
