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

use Builder\Support\Builder;
use Hyperf\Command\Command as HyperfCommand;
use Symfony\Component\Console\Input\InputOption;

use function Hyperf\Support\make;

final class Command extends HyperfCommand
{
    public function __construct()
    {
        parent::__construct('xadmin:build:phar');
    }

    public function configure(): void
    {
        $this->setDescription('将项目代码打包为 PHAR 文件.')
            ->addOption('name', '', InputOption::VALUE_OPTIONAL, 'This is the name of the Phar package, and if it is not passed in, the project name is used by default')
            ->addOption('bin', 'b', InputOption::VALUE_OPTIONAL, 'The script path to execute by default.', 'bin/hyperf.php')
            ->addOption('path', 'p', InputOption::VALUE_OPTIONAL, 'Project root path, default BASE_PATH.')
            ->addOption('phar-version', '', InputOption::VALUE_OPTIONAL, 'The version of the project that will be compiled.')
            ->addOption('mount', 'M', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'The mount path or dir.');
    }

    public function isEnabled(): bool
    {
        // Phar 构建器只属于源码/CI 构建阶段；发布包内部不能再次打包自身。
        return \Phar::running(false) === '';
    }

    public function handle(): void
    {
        $this->assertWritable();
        $bin = $this->input->getOption('bin');
        $name = $this->input->getOption('name');
        $path = $this->input->getOption('path');
        $mount = $this->input->getOption('mount');
        $version = $this->input->getOption('phar-version');

        if (empty($path)) {
            $path = BASE_PATH;
        }
        $builder = $this->getPharBuilder($path);
        if (!empty($bin)) {
            $builder->setMain($bin);
        }
        if (!empty($name)) {
            $builder->setTarget($name);
        }
        if (!empty($version)) {
            $builder->setVersion($version);
        }
        if (count($mount) > 0) {
            $builder->setMount($mount);
        }

        $builder->build();
    }

    /**
     * check readonly.
     */
    public function assertWritable(): void
    {
        if (ini_get('phar.readonly') === '1') {
            throw new \UnexpectedValueException('Your configuration disabled writing phar files (phar.readonly = On), please update your configuration');
        }
    }

    public function getPharBuilder(string $path): Builder
    {
        if (is_dir($path)) {
            $path = rtrim($path, '/') . '/composer.json';
        }
        if (!is_file($path)) {
            throw new \InvalidArgumentException(sprintf('The given path %s is not a readable file', $path));
        }
        $pharBuilder = make(Builder::class, ['path' => $path]);
        if (!is_dir($pharBuilder->getPackage()->getVendorAbsolutePath())) {
            throw new \RuntimeException('The project has not been initialized, please manually execute the command `composer install` to install the dependencies');
        }
        return $pharBuilder;
    }
}
