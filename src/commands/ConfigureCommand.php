<?php

declare(strict_types=1);

namespace winwin\winner\commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use winwin\winner\Config;

class ConfigureCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('configure')
            ->setDescription('Configures gateway');
        $this->addOption('config', null, InputOption::VALUE_REQUIRED, 'config file path');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = Config::getInstance();
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $config->setEndpoint($helper->ask($input, $output,
            $this->createQuestion('网关地址', $config->getEndpoint() ?: 'http://jsonrpc.cuntutu.com')));
        $config->setToken($helper->ask($input, $output,
            $this->createQuestion('API KEY', $config->getToken())));
        $config->setTarsFileRegistryServantName($helper->ask($input, $output,
            $this->createQuestion('Tars文件管理服务名', $config->getTarsFileRegistryServantName() ?: 'WinwinRpc.TarsFileRegistryServer.TarsFileRegistryObj')));
        Config::save($config, $input->getOption('config'));

        return 0;
    }

    protected function createQuestion(string $prompt, $default = null, bool $required = true): Question
    {
        if (!empty($default)) {
            $prompt .= " ($default)";
        }
        $question = new Question($prompt.': ', $default);
        if ($required) {
            $question->setValidator(static function ($value) use ($prompt) {
                if (empty($value)) {
                    throw new \InvalidArgumentException($prompt.' should not be empty');
                }

                return $value;
            });
        }

        return $question;
    }
}
