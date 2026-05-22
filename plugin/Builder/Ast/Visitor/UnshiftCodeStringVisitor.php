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
use PhpParser\Parser;
use PhpParser\ParserFactory;

class UnshiftCodeStringVisitor extends NodeVisitorAbstract
{
    private Parser $astParser;

    public function __construct(public string $code)
    {
        $parserFactory = new ParserFactory();
        $this->astParser = $parserFactory->createForNewestSupportedVersion();
    }

    public function beforeTraverse(array $nodes): ?array
    {
        $stmt = $this->astParser->parse($this->code);
        foreach ($nodes as $i => $node) {
            if ($node instanceof Node\Stmt\Declare_) {
                array_splice($nodes, $i + 1, 0, $stmt);
                return $nodes;
            }
        }

        foreach ($nodes as $i => $node) {
            if (!$node instanceof Node\Stmt\InlineHTML) {
                array_splice($nodes, $i, 0, $stmt);
                return $nodes;
            }
        }

        array_unshift($nodes, $stmt[0]);

        return $nodes;
    }
}
