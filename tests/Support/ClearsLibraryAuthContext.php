<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Tests\Support;

use Hyperf\Context\Context;

trait ClearsLibraryAuthContext
{
    private function clearLibraryAuthContext(): void
    {
        $this->clearContextPrefix('__library.auth.');
        $this->clearContextPrefix('__library.route_annotation.');
    }

    private function clearContextPrefix(string $prefix): void
    {
        foreach (Context::getContainer() as $key => $_) {
            if (is_string($key) && str_starts_with($key, $prefix)) {
                Context::destroy($key);
            }
        }
    }
}
