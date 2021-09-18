<?php

declare(strict_types=1);

namespace winwin\winner\kuiper;

use PHPUnit\Framework\TestCase;

class FixEntityVisitorTest extends TestCase
{
    public function testFix()
    {
        $code = FixEntityVisitor::fix(__DIR__.'/../fixtures/proj/entity.php');
        file_put_contents(__DIR__.'/../fixtures/proj/entity.modified.php', $code);
        echo $code;
    }
}
