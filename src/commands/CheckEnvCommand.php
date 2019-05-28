<?php

namespace winwin\winner\commands;

use Dotenv\Loader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Tag\TaggedValue;
use Symfony\Component\Yaml\Yaml;

class CheckEnvCommand extends Command
{
    use ProjectEnvTrait;

    protected function configure()
    {
        $this->setName('check-env')
            ->setDescription('Check env configuration')
            ->addOption('debug', 'd', InputOption::VALUE_NONE, 'Show variable file and line')
            ->addOption('env', 'e', InputOption::VALUE_REQUIRED, 'The env.yml file')
            ->addOption('dotenv', 't', InputOption::VALUE_NONE, 'treat env file as dot env file')
            ->addArgument('project', InputArgument::REQUIRED, 'Project directory')
            ->addArgument('ignore', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'ignore env setting list', [])
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $ignoreList = ($input->getArgument('ignore'));
        $envFile = $input->getOption('env');
        if (!is_readable($envFile)) {
            throw new \RuntimeException("Cannot read env file '$envFile'");
        }
        if ($input->getOption('dotenv')) {
            $env = [];
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                list($name, $value) = (new Loader(null, null))->processFilters($line, null);
                $env[$name] = $value;
            }
        } else {
            $env = Yaml::parseFile($envFile, Yaml::PARSE_CUSTOM_TAGS);
        }

        $project = $input->getArgument('project');

        $errors = [];
        foreach ($this->extractEnvVariables($project, $output) as $var) {
            if (in_array($var['name'], $ignoreList)) {
                continue;
            }
            if (!isset($env[$var['name']])) {
                $errors['missing'][$var['name']] = $var;
                continue;
            }
            $value = $env[$var['name']];
            $valueIsVaultType = ($value instanceof TaggedValue) && 'vault' == $value->getTag();
            if ('vault' == $var['type'] && !$valueIsVaultType) {
                $errors['not_encrypt'][$var['name']] = $var;
            } elseif ('vault' != $var['type'] && $valueIsVaultType) {
                $errors['should_encrypt'][] = $var;
            }
        }
        if (empty($errors)) {
            $output->writeln('<info>Configuration ok</info>');

            return 0;
        } else {
            $this->report($errors, $input->getOption('debug'), $output);

            return -1;
        }
    }

    private function report($errors, $showLine, OutputInterface $output)
    {
        if (!empty($errors['missing'])) {
            $output->writeln('<error>env 文件中缺少以下配置项：</error>');
            foreach ($errors['missing'] as $var) {
                if ('vault' == $var['type']) {
                    $output->writeln(sprintf("%s: !password ''", $var['name'])
                        .($showLine ? sprintf(' # from %s:%d', $var['file'], $var['line']) : ''));
                } else {
                    $output->writeln(sprintf("%s: ''", $var['name'])
                        .($showLine ? sprintf(' # from %s:%d', $var['file'], $var['line']) : ''));
                }
            }
        }
        if (!empty($errors['not_encrypt'])) {
            $output->writeln('<error>env 文件中以下配置项必须加密：</error>');
            foreach ($errors['not_encrypt'] as $var) {
                $output->writeln(sprintf("%s: !password ''", $var['name'])
                    .($showLine ? sprintf(' # from %s:%d', $var['file'], $var['line']) : ''));
            }
        }
        if (!empty($errors['should_encrypt'])) {
            $output->writeln('<error>以下变量在配置文件中必须使用 winwin\\support\\secret() 函数获取</error>');
            foreach ($errors['should_encrypt'] as $var) {
                $output->writeln(sprintf("secret('%s')", $var['name'])
                    .($showLine ? sprintf(' # from %s:%d', $var['file'], $var['line']) : ''));
            }
        }
    }
}
