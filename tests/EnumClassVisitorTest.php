<?php

namespace winwin\winner;

use PHPUnit\Framework\TestCase;

class EnumClassVisitorTest extends TestCase
{
    public function testScan()
    {
        $visitor = new EnumClassVisitor(__DIR__.'/fixtures/Gender.php');
        $visitor->scan();

        $this->assertEquals(['MALE', 'FEMALE'], $visitor->getValues());
        $this->assertEquals(['description'], $visitor->getProperties());
    }
}
