<?php

declare(strict_types=1);

namespace winwin\winner\linter\error;

use PhpParser\Node;

class ConstantNotFound extends AbstractError
{
    /**
     * @var Node\Name
     */
    private $node;
    /**
     * @var string
     */
    private $constantName;

    public function __construct(Node\Name $node, $constantName)
    {
        $this->node = $node;
        $this->constantName = $constantName;
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription()
    {
        return sprintf('The constant %s::%s not exist', $this->node, $this->constantName);
    }

    public function getNameNode()
    {
        return $this->node;
    }

    public function getConstantName(): string
    {
        return $this->constantName;
    }
}
