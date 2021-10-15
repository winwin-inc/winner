<?php

declare(strict_types=1);

namespace winwin\winner\fixtures\proj;

use kuiper\web\annotation\filter\PreAuthorize;
use winwin\support\web\AbstractController;

class FooController extends AbstractController
{
    /**
     * @PreAuthorize
     */
    public function index(): void
    {
    }
}
