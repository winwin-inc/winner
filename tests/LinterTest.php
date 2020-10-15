<?php

declare(strict_types=1);

namespace winwin\winner;

use PHPUnit\Framework\TestCase;
use winwin\winner\linter\Linter;
use winwin\winner\linter\reporter\TextReporter;

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
        if ($reporter->getErrors()) {
            print_r([$case, $reporter->getErrors()]);
        }
        $this->assertTrue(empty($reporter->getErrors()));
    }

    public function testSingle()
    {
        // $reporter = $this->lint("fail/annotation-class-value");
        $reporter = $this->lint('fail/annotation-value-class-not-found2');
        print_r([(string) $reporter, $reporter->getErrors()]);
        $this->assertTrue(true);
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
            ['const-modifier'],
            ['class-const'],
            ['method-array-param'],
            ['try-catch'],
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
            ['annotation-const'],
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
            ['annotation-const-not-found', 'The constant'],
            ['annotation-value-class-not-found', 'The class Fo'],
            ['not-exist-const', 'The constant'],
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
