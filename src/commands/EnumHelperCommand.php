<?php

declare(strict_types=1);

namespace winwin\winner\commands;

use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use winwin\winner\EnumClassVisitor;

class EnumHelperCommand extends Command
{
    protected function configure()
    {
        $this->setName('enum-helper')
            ->setDescription('Generate enum method and property annotation')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'output file')
            ->addOption('in-place', 'i', null, 'replace file in place')
            ->addArgument('file', InputArgument::REQUIRED, 'The file with php code');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $file = $input->getArgument('file');
        if ('-' === $file) {
            $file = 'php://stdin';
        }
        $content = file_get_contents($file);
        if (false === $content) {
            throw new RuntimeException("Cannot read file '{$file}'");
        }
        $visitor = new EnumClassVisitor($file);
        $visitor->scan();
        $doc = [];
        foreach ($visitor->getValues() as $name) {
            $doc[] = " * @method static {$visitor->getClassName()} {$name}() : static";
        }
        if ($visitor->getProperties()) {
            $doc[] = ' *';
            foreach ($visitor->getProperties() as $property) {
                $doc[] = " * @property string \${$property}";
            }
        }
        $doc = implode("\n", $doc)."\n */\nclass ";
        if (preg_match("#\*\\/\s*\nclass #ms", $content)) {
            $code = preg_replace("#( \* @method.*?)?\*\\/\s*\nclass #ms", $doc, $content);
        } else {
            $code = preg_replace("#\nclass #ms", "\n/**\n".$doc, $content);
        }
        if ($input->getOption('output')) {
            $outFile = $input->getOption('output');
        } elseif ($input->getOption('in-place')) {
            $outFile = $file;
        }
        if (isset($outFile) && 'php://stdin' != $outFile) {
            file_put_contents($outFile, $code);
            $output->writeln("<info>保存代码到 {$outFile}</info>");
        } else {
            echo $code;
        }

        return 0;
    }
}
