<?php

namespace winwin\winner\error;

use PhpParser\Node\Stmt\UseUse;

class UseConflict extends AbstractError
{
    /**
     * @var UseUse
     */
    private $node;

    public function __construct(UseUse $node)
    {
        $this->node = $node;
    }

    public function getDescription()
    {
        return strtr('The import :class name conflicts with previous one', [
            ':class' => $this->node->name,
        ]);
    }

    public function getUseNode()
    {
        return $this->node;
    }
}
