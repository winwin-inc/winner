<?php

declare(strict_types=1);

namespace winwin\winner\kuiper;

use PhpParser\Comment\Doc;
use PhpParser\Lexer\Emulative;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser\Php7;
use PhpParser\PrettyPrinter\Standard;

class FixEntityVisitor extends NodeVisitorAbstract
{
    /**
     * @var bool
     */
    private $fixed = false;
    /**
     * @var string
     */
    private $code;

    public static function fix(string $file): self
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
        $visitor = new self();
        $traverser->addVisitor($visitor);

        $visitor->code = $printer->printFormatPreserving($traverser->traverse($stmts), $stmts, $lexer->getTokens());

        return $visitor;
    }

    public function enterNode(Node $node)
    {
        $re = '#@(var|return|param)\s+\\\\?DateTime\b#';
        if ($node instanceof Node\Stmt\Property
            && null !== $node->getDocComment()
            && preg_match($re, $node->getDocComment()->getText())) {
            $this->fixed = true;
            $node->setDocComment(new Doc(str_replace('DateTime', 'DateTimeInterface', $node->getDocComment()->getText())));
        } elseif ($node instanceof Node\Stmt\ClassMethod
            && null !== $node->getDocComment()
            && preg_match($re, $node->getDocComment()->getText())) {
            $this->fixed = true;
            $node->setDocComment(new Doc(str_replace('DateTime', 'DateTimeInterface', $node->getDocComment()->getText())));
        } elseif ($node instanceof Node\Name
            && in_array($node->toCodeString(), ['DateTime', '\\DateTime'], true)) {
            $this->fixed = true;
            $node->parts[0] = 'DateTimeInterface';
        }

        return null;
    }

    public function isFixed(): bool
    {
        return $this->fixed;
    }

    public function getCode(): string
    {
        return $this->code;
    }
}
