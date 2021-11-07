<?php

declare(strict_types=1);

namespace winwin\winner\kuiper;

use PhpParser\Comment;
use PhpParser\Lexer\Emulative;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser\Php7;
use PhpParser\PrettyPrinter\Standard;

class FixConfigPhpVisitor extends NodeVisitorAbstract
{
    /**
     * @var Node\Expr\Array_
     */
    private $config;
    /**
     * @var string
     */
    private $code;
    /**
     * @var bool
     */
    private $secretImported;
    /**
     * @var bool
     */
    private $hasSecret;

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
        $fixer = new self();
        $traverser->addVisitor($fixer);

        $newStmts = $traverser->traverse($stmts);
        if ($fixer->hasSecret && !$fixer->secretImported) {
            $stmt = new Node\Stmt\Use_(
                [new Node\Stmt\UseUse(new Node\Name('winwin\\support\\secret'))],
                Node\Stmt\Use_::TYPE_FUNCTION
            );

            array_splice($newStmts, 1, 0, [$stmt]);
        }
        $code = $printer->prettyPrintFile($newStmts);
        $fixer->code = preg_replace("#\n\s+\n#ms", "\n", $code);

        return $fixer;
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\UseUse) {
            if ('winwin\\support\\secret' === $node->name->toString()) {
                $this->secretImported = true;
            }
        } elseif ($node instanceof Node\Stmt\Return_ && $node->expr instanceof Node\Expr\Array_) {
            $this->config = $node->expr;
            $web = $this->getNode('application.web');
            if (null !== $web) {
                $this->fixArrayKey($this->getNode('application.web.context-url'));
                $this->fixArrayKey($this->getNode('application.web.error.include-stacktrace'));
                $this->fixArrayKey($this->getNode('application.web.error.display-error'));
            }

            $db = $this->getNode('application.database');
            if (null !== $db) {
                $this->useSecretCall($this->getNode('application.database.user'), 'db.user');
                $this->useSecretCall($this->getNode('application.database.password'), 'db.pass');
                $this->fixArrayKey($this->getNode('application.database.table-prefix'));
            }
            $holo = $this->getNode('application.hologres');
            if (null !== $holo) {
                $this->useSecretCall($this->getNode('application.hologres.user'), 'holo.user');
                $this->useSecretCall($this->getNode('application.hologres.password'), 'holo.pass');
            }

            $redis = $this->getNode('application.redis');
            if (null !== $redis) {
                $this->useSecretCall($this->getNode('application.redis.password'), 'redis.password');
            }
            $http = $this->getNode('application.http-client');
            if (null !== $http) {
                $this->fixHttpClient($http);
            }
            $serverHook = $this->getNode('application.server.enable-hook');
            if (null !== $serverHook) {
                $serverArr = $this->getNode('application.server');
                if (null !== $serverArr && 1 === count($serverArr->value->items)) {
                    $this->removeNode('application.server');
                } else {
                    $this->removeNode('application.server.enable-hook');
                }
            }
            $kmsEnabled = $this->getNode('application.kms.enabled');
            if (null === $kmsEnabled && $this->hasSecret) {
                $this->config->items[0]->value->items[] = new Node\Expr\ArrayItem(
                    new Node\Expr\Array_(
                        [
                            new Node\Expr\ArrayItem(
                                new Node\Expr\BinaryOp\Identical(
                                    new Node\Scalar\String_('true'),
                                    new Node\Expr\FuncCall(
                                        new Node\Name('env'),
                                        [new Node\Arg(new Node\Scalar\String_('KMS_ENABLED'))]
                                    )
                                ),
                                new Node\Scalar\String_('enabled')
                            ),
                        ],
                        ['kind' => Node\Expr\Array_::KIND_SHORT]
                    ),
                    new Node\Scalar\String_('kms')
                );
            }
            $node->expr = $this->config;

            return $node;
        }
        if ($node instanceof Node\Expr\ArrayItem) {
            $node->setAttribute('comments', [new Comment('')]);
        }
        if ($node instanceof Node\Expr\FuncCall
            && isset($node->args[1])
            && !($node->args[1]->value instanceof Node\Scalar\String_)
            && $node->name instanceof Node\Name
            && 'env' === $node->name->toString()) {
            if ($node->args[1]->value instanceof Node\Scalar\LNumber || $node->args[1]->value instanceof Node\Scalar\DNumber) {
                $node->args[1]->value = new Node\Scalar\String_((string) $node->args[1]->value->value);
            } else {
                $node->args[1] = new Node\Expr\FuncCall(new Node\Name('strval'), [$node->args[1]]);
            }

            return $node;
        }

        return null;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    private function getNode(string $name): ?Node\Expr\ArrayItem
    {
        $parts = explode('.', $name);

        return $this->getNodeRecursive($this->config, $parts);
    }

    private function removeNode(string $name): void
    {
        $parts = explode('.', $name);

        $this->removeNodeRecursive($this->config, $parts);
    }

    private function removeNodeRecursive(Node\Expr\Array_ $array, array $path): void
    {
        $name = array_shift($path);
        if (empty($path)) {
            $items = [];
            /** @var Node\Expr\ArrayItem $arrayItem */
            foreach ($array->items as $arrayItem) {
                if (!($arrayItem->key instanceof Node\Scalar\String_ && $arrayItem->key->value === $name)) {
                    $items[] = $arrayItem;
                }
            }
            $array->items = $items;
        } else {
            /** @var Node\Expr\ArrayItem $arrayItem */
            foreach ($array->items as $arrayItem) {
                if ($arrayItem->key instanceof Node\Scalar\String_
                    && $arrayItem->key->value === $name
                    && $arrayItem->value instanceof Node\Expr\Array_) {
                    $this->removeNodeRecursive($arrayItem->value, $path);

                    return;
                }
            }
        }
    }

    public function hasConfig(string $name): bool
    {
        return null !== $this->getNode($name);
    }

    private function getNodeRecursive(Node\Expr\Array_ $array, array $path): ?Node\Expr\ArrayItem
    {
        $name = array_shift($path);
        /** @var Node\Expr\ArrayItem $arrayItem */
        foreach ($array->items as $arrayItem) {
            if ($arrayItem->key instanceof Node\Scalar\String_ && $arrayItem->key->value === $name) {
                if (empty($path)) {
                    return $arrayItem;
                }

                if ($arrayItem->value instanceof Node\Expr\Array_) {
                    return $this->getNodeRecursive($arrayItem->value, $path);
                }
            }
        }

        return null;
    }

    private function useSecretCall(?Node\Expr\ArrayItem $node, string $name): void
    {
        if (null === $node) {
            return;
        }
        $this->hasSecret = true;
        $node->value = new Node\Expr\FuncCall(new Node\Name('secret'), [new Node\Arg(new Node\Scalar\String_($name))]);
    }

    private function fixArrayKey(?Node\Expr\ArrayItem $node): void
    {
        if (null === $node || !($node->key instanceof Node\Scalar\String_)) {
            return;
        }
        $node->key = new Node\Scalar\String_(str_replace('-', '_', $node->key->value));
    }

    public function __toString()
    {
        return $this->code;
    }

    private function fixHttpClient(Node\Expr\ArrayItem $http): void
    {
        $this->fixArrayKey($this->getNode('application.http-client.log-format'));
        $this->fixArrayKey($http);
        $http->value = new Node\Expr\Array_([
            new Node\Expr\ArrayItem(
                $http->value,
                new Node\Scalar\String_('default')
            ),
        ]);
    }
}
