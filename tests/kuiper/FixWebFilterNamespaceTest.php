<?php

declare(strict_types=1);

namespace winwin\winner\kuiper;

use PHPUnit\Framework\TestCase;

class FixWebFilterNamespaceTest extends TestCase
{
    public function testFix()
    {
        $code = FixWebFilterNamespace::fix(__DIR__.'/../fixtures/proj/FooController.php');
        echo $code;
    }
}
