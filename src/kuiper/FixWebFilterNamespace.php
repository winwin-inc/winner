<?php

declare(strict_types=1);

namespace winwin\winner\kuiper;

use PhpParser\Lexer\Emulative;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser\Php7;
use PhpParser\PrettyPrinter\Standard;

class FixWebFilterNamespace extends NodeVisitorAbstract
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
        if (($node instanceof Node\Stmt\UseUse)
            && 0 === strpos($node->name->toString(), 'kuiper\\web\\annotation\\filter')) {
            $node->name = new Node\Name('kuiper\\web\\annotation\\'.$node->name->getLast(), $node->name->getAttributes());
        }

        return null;
    }
}
