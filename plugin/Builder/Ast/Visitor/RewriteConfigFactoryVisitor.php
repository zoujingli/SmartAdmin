<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://github.com/zoujingli/SmartAdmin/blob/master/readme.md
 */

namespace Builder\Ast\Visitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;

class RewriteConfigFactoryVisitor extends NodeVisitorAbstract
{
    protected string $replaceFunc = "<?php
class ConfigFactory{
    private function readPaths(array \$paths)
    {
        \$configs = [];
        \$finder = new Finder();
        \$finder->files()->in(\$paths)->name('*.php');
        foreach (\$finder as \$file) {
            \$config = [];
            \$key = implode('.', array_filter([
                str_replace('/', '.', \$file->getRelativePath()),
                \$file->getBasename('.php'),
            ]));
            \\Hyperf\\Collection\\Arr::set(\$config, \$key, require \$file->getPathname());
            \$configs[] = \$config;
        }
        return \$configs;
     }
}";

    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Class_) {
            if (is_array($node->stmts) && !empty($node->stmts)) {
                foreach ($node->stmts as $key => $method) {
                    if ($method instanceof Node\Stmt\ClassMethod) {
                        if ($method->name->name == 'readPaths') {
                            $result = $this->createReplaceFunc();
                            if (!empty($result)) {
                                $node->stmts[$key] = $result;
                            }
                        }
                    }
                }
            }
        }
        return $node;
    }

    public function createReplaceFunc()
    {
        $parserFactory = new ParserFactory();
        $parser = $parserFactory->createForNewestSupportedVersion();
        $stmts = $parser->parse($this->replaceFunc);
        if (empty($stmts)) {
            return null;
        }
        foreach ($stmts as $node) {
            if ($node instanceof Node\Stmt\Class_ && !empty($node->stmts) && is_array($node->stmts)) {
                foreach ($node->stmts as $val) {
                    return $val;
                }
            }
        }
        return null;
    }
}
