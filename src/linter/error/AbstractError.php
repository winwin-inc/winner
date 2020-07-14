<?php

declare(strict_types=1);

namespace winwin\winner\linter\error;

abstract class AbstractError implements ErrorInterface
{
    /**
     * @var string the file name
     */
    private $file;

    /**
     * @var string the line
     */
    private $line;

    /**
     * {@inheritdoc}
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * {@inheritdoc}
     */
    public function setFile($file)
    {
        $this->file = $file;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getLine()
    {
        return $this->line;
    }

    /**
     * {@inheritdoc}
     */
    public function setLine($line)
    {
        $this->line = $line;

        return $this;
    }
}
