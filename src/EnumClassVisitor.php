<?php

declare(strict_types=1);

namespace winwin\winner;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;

class EnumClassVisitor extends NodeVisitor
{
    /**
     * @var string
     */
    private $file;

    /**
     * @var resource
     */
    private $stream;

    /**
     * @var string
     */
    private $className;

    /**
     * @var string[]
     */
    private $values = [];

    /**
     * @var string[]
     */
    private $properties = [];

    /**
     * Constructor.
     *
     * @param string|resource $source file name or code
     */
    public function __construct($source)
    {
        parent::__construct();
        if (is_resource($source)) {
            $this->stream = $source;
        } else {
            $this->file = $source;
        }
    }

    public function scan(): void
    {
        $code = $this->stream
            ? stream_get_contents($this->stream)
            : file_get_contents($this->file);
        $parser = (new ParserFactory())->create(ParserFactory::ONLY_PHP7);
        $traverser = new NodeTraverser();
        $statements = $parser->parse($code);
        $traverser->addVisitor($this);
        $traverser->traverse($statements);
    }

    /**
     * @return string[]
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * @return string[]
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    public function enterClass(Node\Stmt\Class_ $node): void
    {
        $this->className = $node->name->toString();
    }

    public function enterConst(Node\Stmt\ClassConst $node): void
    {
        $this->values[] = $node->consts[0]->name->toString();
    }

    public function enterProperty(Node\Stmt\Property $node): void
    {
        if ($node->isStatic() && 'PROPERTIES' === $node->props[0]->name->toString()) {
            foreach ($node->props[0]->default->items as $item) {
                $this->properties[] = $item->key->value;
            }
        }
    }
}
