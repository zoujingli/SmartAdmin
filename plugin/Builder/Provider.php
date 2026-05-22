<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://github.com/zoujingli/SmartAdmin/blob/master/readme.md
 */

namespace Builder;

final class Provider
{
    public function __invoke(): array
    {
        $commands = [];
        $commands[] = Command::class;
        return ['commands' => $commands];
    }
}
