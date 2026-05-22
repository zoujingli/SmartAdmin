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

/**
 * @mixin  \Builder\Support\Custom
 */
class Target implements \Stringable
{
    public function __construct(private readonly Custom $phar, private readonly Builder $pharBuilder)
    {
        $phar->startBuffering();
    }

    public function __toString(): string
    {
        $exploded = explode('/', $this->phar->getPath());
        return end($exploded);
    }

    /**
     * 魔术调用方法.
     */
    public function __call(string $name, array $arguments): mixed
    {
        return $this->phar->{$name}(...$arguments);
    }

    /**
     * Stop writing the Phar package.
     */
    public function stopBuffering(): void
    {
        $this->phar->stopBuffering();
    }

    /**
     * Add a resource bundle to the Phar package.
     */
    public function addBundle(Bundle $bundle): void
    {
        /** @var Finder|string $resource */
        foreach ($bundle as $resource) {
            is_string($resource) ? $this->addFile($resource) : $this->buildFromIterator($resource);
        }
    }

    /**
     * Add the file to the Phar package.
     */
    public function addFile(string $filename): void
    {
        $this->phar->addFile($filename, $this->pharBuilder->getPathLocalToBase($filename));
    }

    /**
     * Add folder resources to the Phar package.
     */
    public function buildFromIterator(\Traversable $iterator): void
    {
        /* @phpstan-ignore-next-line */
        $this->phar->buildFromIterator($iterator, $this->pharBuilder->getPackage()->getDirectory());
    }

    /**
     * Create the default execution file.
     */
    public function createDefaultStub(string $indexFile, ?string $webIndexFile = null): string
    {
        $params = [$indexFile];
        if ($webIndexFile != null) {
            $params[] = $webIndexFile;
        }
        return '#!/usr/bin/env php' . PHP_EOL . $this->phar->createDefaultStub(...$params);
    }

    /**
     * Set the default startup file.
     */
    public function setStub(string $stub): void
    {
        $this->phar->setStub($stub);
    }

    /**
     * Add a string to the Phar package.
     */
    public function addFromString(string $local, string $contents): void
    {
        $this->phar->addFromString($local, $contents);
    }

    /**
     * 保存文件.
     */
    public function save(): void
    {
        $this->phar->save();
    }
}
