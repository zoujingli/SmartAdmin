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
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

/**
 * 方法体混淆访问器。
 *
 * 仅处理函数、方法和闭包内部语句，保留类名、方法名、属性名、参数签名和 PHP 8 Attribute，
 * 避免破坏 Hyperf 路由扫描、依赖注入、反射调用和插件注册等运行时契约。
 */
final class ObfuscateFunctionBodyVisitor extends NodeVisitorAbstract
{
    /**
     * PHP 运行时特殊变量和超全局变量不能重命名。
     */
    private const RESERVED_VARIABLES = [
        'this' => true,
        '_SERVER' => true,
        '_GET' => true,
        '_POST' => true,
        '_FILES' => true,
        '_COOKIE' => true,
        '_SESSION' => true,
        '_REQUEST' => true,
        '_ENV' => true,
        'GLOBALS' => true,
        'http_response_header' => true,
    ];

    /**
     * 这些函数依赖变量名或当前符号表；命中后当前方法体只编码字符串，不改局部变量名。
     */
    private const VARIABLE_NAME_SENSITIVE_FUNCTIONS = [
        'compact' => true,
        'extract' => true,
        'get_defined_vars' => true,
        'parse_str' => true,
        'mb_parse_str' => true,
    ];

    public function __construct(private readonly string $seed) {}

    public function leaveNode(Node $node): Node
    {
        if (!$node instanceof Node\FunctionLike || $node instanceof Node\Expr\ArrowFunction) {
            return $node;
        }

        $stmts = $node->getStmts();
        if ($stmts === null || $stmts === []) {
            return $node;
        }

        $variables = [];
        $renameEnabled = !$this->hasVariableRenameRisk($stmts);
        if ($renameEnabled) {
            $reserved = $this->collectReservedVariableNames($node);
            $variables = $this->collectVariableNames($stmts, $reserved);
        }

        $mapping = $this->createVariableMapping($variables);
        $node->stmts = $this->rewriteNodes($stmts, $mapping, true);
        return $node;
    }

    /**
     * 收集签名和闭包捕获变量；这些变量名属于外部可观测契约，不能混淆。
     *
     * @return array<string, true>
     */
    private function collectReservedVariableNames(Node\FunctionLike $node): array
    {
        $reserved = self::RESERVED_VARIABLES;
        foreach ($node->getParams() as $param) {
            if ($param->var instanceof Node\Expr\Variable && is_string($param->var->name)) {
                $reserved[$param->var->name] = true;
            }
        }
        if ($node instanceof Node\Expr\Closure) {
            foreach ($node->uses as $use) {
                if ($use->var instanceof Node\Expr\Variable && is_string($use->var->name)) {
                    $reserved[$use->var->name] = true;
                }
            }
        }
        return $reserved;
    }

    /**
     * 检测变量变量、闭包捕获、箭头函数和符号表函数等风险语法。
     */
    private function hasVariableRenameRisk(array $nodes): bool
    {
        foreach ($nodes as $node) {
            if (!$node instanceof Node) {
                continue;
            }
            if ($node instanceof Node\Expr\ArrowFunction) {
                return true;
            }
            if ($node instanceof Node\Expr\Closure && $node->uses !== []) {
                return true;
            }
            if ($node instanceof Node\Expr\Variable && !is_string($node->name)) {
                return true;
            }
            if ($node instanceof Node\Stmt\Global_) {
                return true;
            }
            if ($node instanceof Node\Stmt\ClassLike) {
                continue;
            }
            if ($node instanceof Node\Expr\FuncCall && $node->name instanceof Node\Name) {
                $name = strtolower($node->name->toString());
                if (isset(self::VARIABLE_NAME_SENSITIVE_FUNCTIONS[$name])) {
                    return true;
                }
            }
            if ($node instanceof Node\FunctionLike) {
                continue;
            }
            foreach ($node->getSubNodeNames() as $name) {
                $value = $node->{$name};
                if ($value instanceof Node && $this->hasVariableRenameRisk([$value])) {
                    return true;
                }
                if (is_array($value) && $this->hasVariableRenameRisk($value)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * 收集当前方法体内可安全改名的局部变量；嵌套函数体独立处理，不进入其内部。
     *
     * @param array<string, true> $reserved
     * @return string[]
     */
    private function collectVariableNames(array $nodes, array $reserved): array
    {
        $variables = [];
        foreach ($nodes as $node) {
            if (!$node instanceof Node) {
                continue;
            }
            if ($node instanceof Node\Expr\Variable && is_string($node->name) && !isset($reserved[$node->name])) {
                $variables[$node->name] = true;
            }
            if ($node instanceof Node\FunctionLike || $node instanceof Node\Stmt\ClassLike) {
                continue;
            }
            foreach ($node->getSubNodeNames() as $name) {
                $value = $node->{$name};
                if ($value instanceof Node) {
                    foreach ($this->collectVariableNames([$value], $reserved) as $variable) {
                        $variables[$variable] = true;
                    }
                } elseif (is_array($value)) {
                    foreach ($this->collectVariableNames($value, $reserved) as $variable) {
                        $variables[$variable] = true;
                    }
                }
            }
        }
        return array_keys($variables);
    }

    /**
     * 生成稳定但不可读的局部变量名，避免同一源码多次构建产生无意义差异。
     *
     * @param string[] $variables
     * @return array<string, string>
     */
    private function createVariableMapping(array $variables): array
    {
        sort($variables);
        $mapping = [];
        foreach ($variables as $index => $variable) {
            $mapping[$variable] = '_x' . substr(sha1($this->seed . ':' . $index . ':' . $variable), 0, 10);
        }
        return $mapping;
    }

    /**
     * 重写当前方法体：局部变量改名、字符串字面量编码；嵌套函数体由外层遍历器单独处理。
     *
     * @param array<string, string> $mapping
     */
    private function rewriteNodes(array $nodes, array $mapping, bool $encodeString): array
    {
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new class($mapping, $encodeString, $this->seed) extends NodeVisitorAbstract {
            public function __construct(
                private readonly array $mapping,
                private readonly bool $encodeString,
                private readonly string $seed
            ) {}

            public function enterNode(Node $node): int|Node|null
            {
                if ($node instanceof Node\FunctionLike) {
                    return NodeTraverser::DONT_TRAVERSE_CHILDREN;
                }
                if ($node instanceof Node\Stmt\ClassLike) {
                    return NodeTraverser::DONT_TRAVERSE_CHILDREN;
                }
                if ($node instanceof Node\Stmt\Static_) {
                    // static 局部变量默认值必须保持常量表达式，不能替换成运行时解码调用；变量名仍需同步改名。
                    foreach ($node->vars as $staticVar) {
                        if ($staticVar->var instanceof Node\Expr\Variable && is_string($staticVar->var->name) && isset($this->mapping[$staticVar->var->name])) {
                            $staticVar->var->name = $this->mapping[$staticVar->var->name];
                        }
                    }
                    return NodeTraverser::DONT_TRAVERSE_CHILDREN;
                }
                return null;
            }

            public function leaveNode(Node $node): Node
            {
                if ($node instanceof Node\Expr\Variable && is_string($node->name) && isset($this->mapping[$node->name])) {
                    $node->name = $this->mapping[$node->name];
                    return $node;
                }
                if ($node instanceof Node\Stmt\Catch_ && $node->var instanceof Node\Expr\Variable && is_string($node->var->name) && isset($this->mapping[$node->var->name])) {
                    $node->var->name = $this->mapping[$node->var->name];
                    return $node;
                }
                if ($this->encodeString && $node instanceof Node\Scalar\String_ && $node->value !== '') {
                    return $this->createDecodeCall($node->value);
                }
                if ($this->encodeString && $node instanceof Node\Scalar\Encapsed) {
                    return $this->createConcatFromEncapsed($node);
                }
                return $node;
            }

            /**
             * 字符串运行时解码，值等价但发布包中不再直接暴露完整明文。
             */
            private function createDecodeCall(string $value): Node\Expr\FuncCall
            {
                $key = (crc32($this->seed . ':' . $value) % 253) + 1;
                $encoded = '';
                for ($index = 0, $length = strlen($value); $index < $length; ++$index) {
                    $encoded .= chr(ord($value[$index]) ^ $key);
                }
                return new Node\Expr\FuncCall(new Node\Name('\\xadmin_obf_s'), [
                    new Node\Arg(new Node\Scalar\String_(strrev(base64_encode($encoded)))),
                    new Node\Arg(new Node\Scalar\LNumber($key)),
                ]);
            }

            /**
             * 将插值字符串拆成“混淆后的字面量 + 动态表达式”的拼接表达式，避免双引号片段继续暴露明文。
             */
            private function createConcatFromEncapsed(Node\Scalar\Encapsed $node): Node\Expr|Node\Scalar\String_
            {
                $expr = null;
                $parts = 0;
                $dynamicParts = 0;
                foreach ($node->parts as $part) {
                    if ($part instanceof Node\Scalar\EncapsedStringPart) {
                        if ($part->value === '') {
                            continue;
                        }
                        $partExpr = $this->createDecodeCall($part->value);
                    } else {
                        $partExpr = $part;
                        ++$dynamicParts;
                    }

                    $expr = $expr === null ? $partExpr : new Node\Expr\BinaryOp\Concat($expr, $partExpr);
                    ++$parts;
                }
                if ($expr === null) {
                    return new Node\Scalar\String_('');
                }
                // "{$item}" 这类纯变量插值在原语义中会先转字符串；直接返回变量可能在 PHP 8 触发参数类型错误。
                return $parts === 1 && $dynamicParts === 1 ? new Node\Expr\Cast\String_($expr) : $expr;
            }
        });
        return $traverser->traverse($nodes);
    }
}
