<?php

declare(strict_types=1);

namespace winwin\winner\commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenEnvCommand extends Command
{
    use ProjectEnvTrait;

    protected function configure()
    {
        $this->setName('gen-env')
            ->setDescription('生成 env 文件')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output file')
            ->addArgument('project', InputArgument::OPTIONAL, 'Project directory', '.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $project = $input->getArgument('project');
        $outFile = $input->getOption('output');
        if (!$outFile) {
            $outFile = 'php://stdout';
        }
        $vars = $this->extractEnvVariables($project, $output);
        $seen = [];
        $lines = [];
        foreach ($vars as $var) {
            if (isset($seen[$var['name']])) {
                continue;
            }
            $seen[$var['name']] = true;
            $lines[] = $var['name'].'=';
        }
        file_put_contents($outFile, implode("\n", $lines)."\n");

        if ($output->isVerbose() && 'php://stdout' !== $outFile) {
            $output->writeln("<info>Save to $outFile</info>");
        }

        return 0;
    }
}
