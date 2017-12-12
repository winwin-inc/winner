<?php

namespace winwin\winner\error;

use PhpParser\Error;

class SyntaxError extends AbstractError
{
    /**
     * @var Error
     */
    private $error;

    public function __construct(Error $error)
    {
        $this->error = $error;
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription()
    {
        return 'Syntax error: '.$this->error->getRawMessage();
    }

    public function getError()
    {
        return $this->error;
    }
}
