<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://github.com/zoujingli/SmartAdmin/blob/master/readme.md
 */
use Hyperf\Context\ApplicationContext;
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSourceFactory;
use Hyperf\Di\Exception\Exception;

try {
    return ApplicationContext::setContainer(new Container((new DefinitionSourceFactory())()));
} catch (Exception $e) {
    throw new RuntimeException('The dependency injection container is invalid.');
}
