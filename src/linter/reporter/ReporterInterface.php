<?php

declare(strict_types=1);

namespace winwin\winner\linter\reporter;

use winwin\winner\linter\error\ErrorInterface;

interface ReporterInterface
{
    /**
     * @return self
     */
    public function add(ErrorInterface $error);
}
