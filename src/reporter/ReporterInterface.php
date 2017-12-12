<?php

namespace winwin\winner\reporter;

use winwin\winner\error\ErrorInterface;

interface ReporterInterface
{
    /**
     * @param ErrorInterface $error
     *
     * @return self
     */
    public function add(ErrorInterface $error);
}
