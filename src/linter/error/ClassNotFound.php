<?php

declare(strict_types=1);

namespace winwin\winner\linter\error;

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
