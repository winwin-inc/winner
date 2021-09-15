<?php

declare(strict_types=1);

namespace winwin\winner\commands;

use PHPUnit\Framework\TestCase;
use winwin\winner\kuiper\FixConfigPhpVisitor;
use winwin\winner\kuiper\FixIndexPhpVisitor;

class KuiperUpgradeCommandTest extends TestCase
{
    public function testFixIndexPhp()
    {
        $code = FixIndexPhpVisitor::fix(__DIR__.'/../fixtures/proj/index.php');
        // file_put_contents(__DIR__.'/../fixtures/proj/index.modified.php', $code);
        $this->assertEquals(file_get_contents(__DIR__.'/../fixtures/proj/index.modified.php'), $code);
    }

    public function testFixConfigPhp()
    {
        $code = FixConfigPhpVisitor::fix(__DIR__.'/../fixtures/proj/config.php');
        file_put_contents(__DIR__.'/../fixtures/proj/config.modified.php', $code);
        echo $code;
    }
}
