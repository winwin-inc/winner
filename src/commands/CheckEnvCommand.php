<?php

declare(strict_types=1);

namespace winwin\winner\commands;

use Dotenv\Dotenv;
use Dotenv\Repository\Adapter\ArrayAdapter;
use Dotenv\Repository\RepositoryBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CheckEnvCommand extends Command
{
    use ProjectEnvTrait;

    protected function configure()
    {
        $this->setName('check-env')
            ->setDescription('Check env configuration')
            ->addOption('debug', 'd', InputOption::VALUE_NONE, 'Show variable file and line')
            ->addOption('env', 'e', InputOption::VALUE_REQUIRED, 'The env.yml file')
            ->addArgument('project', InputArgument::REQUIRED, 'Project directory')
            ->addArgument('ignore', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'ignore env setting list', []);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $ignoreList = ($input->getArgument('ignore'));
        $envFile = $input->getOption('env');
        if (!is_readable($envFile)) {
            throw new \RuntimeException("Cannot read env file '$envFile'");
        }

        $project = $input->getArgument('project');
        $env = $this->loadDotenv($project, $envFile);

        $errors = [];
        foreach ($this->extractEnvVariables($project, $output) as $var) {
            if (in_array($var['name'], $ignoreList, true)) {
                continue;
            }
            if (!isset($env[$var['name']])) {
                $errors['missing'][$var['name']] = $var;
                continue;
            }
        }
        if (empty($errors)) {
            $output->writeln('<info>Configuration ok</info>');

            return 0;
        }

        $this->report($errors, $input->getOption('debug'), $output);

        return -1;
    }

    private function report($errors, $showLine, OutputInterface $output): void
    {
        if (!empty($errors['missing'])) {
            $output->writeln('<error>env 文件中缺少以下配置项：</error>');
            foreach ($errors['missing'] as $var) {
                $output->writeln(sprintf("%s=''", $var['name'])
                    .($showLine ? sprintf(' # from %s:%d', $var['file'], $var['line']) : ''));
            }
        }
    }

    protected function loadDotenv($project, $envFile): array
    {
        $repository = RepositoryBuilder::createWithNoAdapters()
            ->addWriter(ArrayAdapter::class)
            ->make();

        return Dotenv::create($repository, $project, [$envFile])
            ->load();
    }
}
