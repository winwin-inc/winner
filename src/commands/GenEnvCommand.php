<?php

namespace winwin\winner\commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Tag\TaggedValue;
use Symfony\Component\Yaml\Yaml;

class GenEnvCommand extends Command
{
    use ProjectEnvTrait;

    protected function configure()
    {
        $this->setName('gen-env')
            ->setDescription('生成 env 文件')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output file')
            ->addArgument('project', InputArgument::REQUIRED, 'Project directory');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $project = $input->getArgument('project');
        $outFile = $input->getOption('output');
        if (!$outFile) {
            $outFile = 'php://stdout';
        }
        $vars = $this->extractEnvVariables($project, $output);
        $env = [];
        foreach ($vars as $var) {
            if ('vault' == $var['type']) {
                $env[$var['name']] = new TaggedValue('password', '');
            } else {
                $env[$var['name']] = '';
            }
        }
        file_put_contents($outFile, Yaml::dump($env));

        if ($output->isVerbose() && 'php://stdout' != $outFile) {
            $output->writeln("<info>Save to $outFile</info>");
        }
    }
}
