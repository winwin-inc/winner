<?php

declare(strict_types=1);

namespace winwin\winner\kuiper;

use PhpParser\Lexer\Emulative;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser\Php7;
use PhpParser\PrettyPrinter\Standard;

class FixIndexPhpVisitor extends NodeVisitorAbstract
{
    public static function fix(string $file): string
    {
        $lexer = new Emulative([
            'usedAttributes' => [
                'comments',
                'startLine', 'endLine',
                'startTokenPos', 'endTokenPos',
            ],
        ]);
        $parser = new Php7($lexer);
        $printer = new Standard();
        $stmts = $parser->parse(file_get_contents($file));
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new self());

        return $printer->printFormatPreserving($traverser->traverse($stmts), $stmts, $lexer->getTokens());
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\UseUse) {
            if ('wenbinye\\tars\\server\\ServerApplication' === $node->name->toString()) {
                $node->name = new Node\Name('kuiper\\tars\\TarsApplication', $node->name->getAttributes());
            }
        } elseif ($node instanceof Node\Expr\StaticCall && 'ServerApplication' === $node->class->toString()) {
            $node->class = new Node\Name('TarsApplication', $node->class->getAttributes());
        }

        return null;
    }
}
