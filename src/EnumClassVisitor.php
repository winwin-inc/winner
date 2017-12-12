<?php

namespace winwin\winner;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
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
     * @param string $source file name or code
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

    public function scan()
    {
        $code = $this->stream
            ? stream_get_contents($this->stream)
            : file_get_contents($this->file);
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP5);
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

    public function enterConst(Node\Stmt\ClassConst $node)
    {
        // print_r($node->consts);
        $this->values[] = $node->consts[0]->name;
    }

    public function enterProperty(Node\Stmt\Property $node)
    {
        if ($node->type & Class_::MODIFIER_STATIC
            && $node->props[0]->name == 'PROPERTIES') {
            foreach ($node->props[0]->default->items as $item) {
                $this->properties[] = $item->key->value;
            }
        }
    }
}
