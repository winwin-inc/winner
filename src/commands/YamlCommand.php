<?php

namespace winwin\winner\commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class YamlCommand extends Command
{
    const BASE_KEY = '.base';

    protected function configure()
    {
        $this->setName('yaml')
            ->setDescription('Yaml processor')
            ->addOption('base', null, InputOption::VALUE_REQUIRED, 'base key name', self::BASE_KEY)
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output file')
            ->addArgument('input', InputArgument::REQUIRED, 'Input yaml file');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $baseKey = $input->getOption('base');
        $file = $input->getArgument('input');
        if ($file == '-') {
            $file = 'php://stdin';
        }
        $outFile = $input->getOption('output');
        if (!$outFile) {
            $outFile = 'php://stdout';
        }
        $data = Yaml::parse(file_get_contents($file), Yaml::PARSE_CUSTOM_TAGS);
        $sections = [];
        foreach ($data as $key => $values) {
            $sections[$key] = Yaml::dump($values);
        }
        foreach ($sections as $key => $content) {
            if ($key == $baseKey) {
                continue;
            }
            $sections[$key] = str_replace($sections[$baseKey], "<<: *{$baseKey}\n", $content);
        }
        $yaml = '';
        foreach ($sections as $key => $content) {
            if ($key == $baseKey) {
                $yaml .= "$baseKey: &$baseKey\n";
            } else {
                $yaml .= "$key: \n";
            }
            $yaml .= preg_replace('#^#ms', '    ', $content)."\n";
        }
        file_put_contents($outFile, $yaml);

        if ($output->isVerbose() && $outFile != 'php://stdout') {
            $output->writeln("<info>Save to $outFile</info>");
        }
    }
}
