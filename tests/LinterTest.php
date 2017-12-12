<?php

namespace winwin\winner;

use PHPUnit\Framework\TestCase;
use winwin\winner\reporter\TextReporter;

/**
 * TestCase for PhpLint.
 */
class LinterTest extends TestCase
{
    /**
     * @dataProvider passedScripts
     */
    public function testOk($case)
    {
        $reporter = $this->lint('pass/'.$case);
        // print_r($reporter->getErrors());
        $this->assertTrue(empty($reporter->getErrors()));
    }

    /**
     * @dataProvider failedScripts
     */
    public function testFail($case, $error)
    {
        $report = (string) $this->lint('fail/'.$case);
        $this->assertStringStartsWith('Fatal error: '.$error, $report);
    }

    public function passedScripts()
    {
        return [
            ['new-self'],
            ['new-static'],
            ['extends-full'],
            ['extends-imported'],
            ['funcall'],
            ['funcall-variable'],
            ['use-namespace'],
            ['use-function'],
            ['property-type'],
            ['class-annotation-imported'],
            ['method-parameter'],
            ['method-param'],
            ['method-return'],
            ['annotation'],
            ['annotation-see'],
        ];
    }

    public function failedScripts()
    {
        return [
            ['property-type', 'The class Bar'],
            ['syntax-error', 'Syntax error'],
            ['use-conflict', 'The import Phalcon\Config'],
            ['extends', 'The class Bar'],
            ['implements', 'The class Bar'],
            ['class-annotation', 'The class Bar'],
            ['method-annotation', 'The class Bar'],
            ['property-annotation', 'The class Bar'],
            ['method-parameter', 'The class Bar'],
            ['method-param', 'The class Bar'],
            ['method-return', 'The class Bar'],
            ['new-class', 'The class Bar'],
            ['class-funcall', 'The function Bar'],
            ['instanceof', 'The class Bar'],
            ['try-catch', 'The class Exception'],
        ];
    }

    private function lint($case)
    {
        $file = __DIR__.'/fixtures/'.$case.'.php';

        return (new Linter($file, new TextReporter()))
            ->lint()
            ->getReporter();
    }
}
