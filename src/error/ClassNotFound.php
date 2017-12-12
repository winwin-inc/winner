<?php

namespace winwin\winner\error;

use PhpParser\Node;

class ClassNotFound extends AbstractError
{
    /**
     * @var Node\Name
     */
    private $node;

    public function __construct(Node\Name $node)
    {
        $this->node = $node;
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription()
    {
        return sprintf('The class %s not exist', $this->node);
    }

    public function getNameNode()
    {
        return $this->node;
    }
}
