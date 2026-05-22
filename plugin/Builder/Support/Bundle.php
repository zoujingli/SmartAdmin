<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://github.com/zoujingli/SmartAdmin/blob/master/readme.md
 */

namespace Builder\Support;

use Symfony\Component\Finder\Finder;

class Bundle implements \IteratorAggregate
{
    /**
     * @var Finder[]|string[]
     */
    private array $resources = [];

    /**
     * Add a file to the resource bundle.
     */
    public function addFile(string $file): static
    {
        $this->resources[] = $file;
        return $this;
    }

    /**
     * Add a directory package to a resource package.
     */
    public function addFinder(Finder $dir): static
    {
        $this->resources[] = $dir;
        return $this;
    }

    /**
     * Returns an iterator for a list of resources.
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->resources);
    }
}
