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
            ->addOption('staging', null, InputOption::VALUE_REQUIRED, 'env staging names', 'prod,dev')
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
        $staging = $input->getOption('staging');
        $stagings = explode(',', $staging);
        $vars = $this->extractEnvVariables($project, $output);
        $env = [];
        foreach ($vars as $var) {
            if ($var['type'] == 'vault') {
                foreach ($stagings as $staging) {
                    $env[$staging][$var['name']] = new TaggedValue('password', '');
                }
            } else {
                $env[YamlCommand::BASE_KEY][$var['name']] = '';
            }
        }
        $yaml = '';
        foreach ($env as $staging => $vars) {
            if ($staging == YamlCommand::BASE_KEY) {
                $yaml .= "$staging: &$staging\n";
            } else {
                $yaml .= "$staging:\n";
                $yaml .= '  <<: &'.YamlCommand::BASE_KEY."\n";
            }
            $yaml .= preg_replace('#^#ms', '  ', Yaml::dump($vars))."\n";
        }
        file_put_contents($outFile, $yaml);

        if ($output->isVerbose() && $outFile != 'php://stdout') {
            $output->writeln("<info>Save to $outFile</info>");
        }
    }
}
