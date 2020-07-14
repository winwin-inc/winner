<?php

declare(strict_types=1);

namespace winwin\winner\commands;

use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use winwin\winner\Config;
use winwin\winner\JsonRpcGatewayClient;
use winwin\winner\TarsPackage;

abstract class AbstractCommand extends Command
{
    private const CONFIG_FILE = 'tars/config.json';

    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var JsonRpcGatewayClient
     */
    private $gatewayClient;

    protected function configure(): void
    {
        $this->addOption('config', null, InputOption::VALUE_REQUIRED, 'config file path');
        $this->addOption('debug', null, InputOption::VALUE_NONE, 'show debug');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;
        $this->handle();

        return 0;
    }

    protected function getTarsFileRegistryServant(): string
    {
        return $this->getGatewayClient()->getConfig()->getTarsFileRegistryServantName();
    }

    protected function getGatewayClient(): JsonRpcGatewayClient
    {
        if (!$this->gatewayClient) {
            $logger = new Logger('Winner', [new ErrorLogHandler()]);
            $config = $this->input->getOption('config')
                ? Config::read($this->input->getOption('config'))
                : Config::getInstance();
            if (!$config->getEndpoint()) {
                throw new \InvalidArgumentException('API not config. Use config command set endpoint first.');
            }
            $this->gatewayClient = new JsonRpcGatewayClient($config, $logger, $this->input->getOption('debug'));
        }

        return $this->gatewayClient;
    }

    /**
     * @return TarsPackage[]
     */
    protected function loadTarsPackages(): array
    {
        $packages = [];
        if (file_exists(self::CONFIG_FILE)) {
            $config = json_decode(file_get_contents(self::CONFIG_FILE), true);
            if (isset($config['packages'])) {
                foreach ($config['packages'] as $name => $item) {
                    $packages[$name] = new TarsPackage(
                        $name,
                        $item['revision'] ?? 'master',
                        $item['files'] ?? [],
                        $item['path'] ?? ''
                    );
                }
            }
        }

        return $packages;
    }

    protected function saveTarsPackage(TarsPackage $tarsPackage): void
    {
        $config = [];
        if (file_exists(self::CONFIG_FILE)) {
            $config = json_decode(file_get_contents(self::CONFIG_FILE), true);
        }
        $config['packages'][$tarsPackage->getName()] = array_filter([
            'revision' => $tarsPackage->getRevision(),
            'files' => $tarsPackage->getFiles(),
            'path' => $tarsPackage->getPathPrefix(),
        ]);
        file_put_contents(self::CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    abstract protected function handle(): void;
}
