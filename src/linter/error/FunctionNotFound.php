<?php

declare(strict_types=1);

namespace winwin\winner\linter\error;

use PhpParser\Node;

class FunctionNotFound extends AbstractError
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
        return sprintf('The function %s not exist', $this->node);
    }

    public function getNameNode()
    {
        return $this->node;
    }
}
