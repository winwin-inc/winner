<?php

namespace winwin\winner\fixtures;

use PHPUnit\Framework\TestCase;
use winwin\winner\TypeUtils;

class TypeUtilsTest extends TestCase
{
    public function testParseComposite()
    {
        $type = TypeUtils::parse('(Bar|null)[]');
        $this->assertEquals($type, [
            'isa' => 'array',
            'valueType' => [
                'isa' => 'composite',
                'types' => [
                    [
                        'isa' => 'class',
                        'class' => 'Bar',
                    ],
                    [
                        'isa' => 'primitive',
                        'type' => 'null',
                    ],
                ],
            ],
        ]);
    }
}
