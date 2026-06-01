<?php

declare(strict_types=1);

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
