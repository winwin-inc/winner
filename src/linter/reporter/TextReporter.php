<?php

declare(strict_types=1);

namespace winwin\winner\linter\reporter;

use winwin\winner\linter\error\ErrorInterface;

class TextReporter implements ReporterInterface
{
    /**
     * @var array
     */
    private $errors = [];

    /**
     * {@inheritdoc}
     */
    public function add(ErrorInterface $error)
    {
        $this->errors[] = 'Fatal error: '.$error->getDescription()
                        .$this->getLineInfo($error->getFile(), $error->getLine());
    }

    public function __toString()
    {
        return implode("\n", $this->errors);
    }

    public function getErrors()
    {
        return $this->errors;
    }

    private function getLineInfo($file, $line)
    {
        $info = $file ? ' in '.$file.' ' : ' ';
        if (-1 == $line) {
            return $info.'on unknown line';
        } else {
            return $info.'on line '.$line;
        }
    }
}
