<?php

declare(strict_types=1);

namespace winwin\winner\linter\error;

class AnnotationError extends AbstractError
{
    /**
     * @var string
     */
    private $description;

    public function __construct($description)
    {
        $this->description = $description;
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription()
    {
        return $this->description;
    }
}
