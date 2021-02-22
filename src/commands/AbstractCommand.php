<?php

declare(strict_types=1);

namespace winwin\winner\commands;

use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use winwin\winner\Config;
use winwin\winner\JsonRpcGatewayClient;
use winwin\winner\TarsPackage;

abstract class AbstractCommand extends Command
{
    private const CONFIG_FILE = TarsPackage::TARS_FILE_PATH.'/config.json';

    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var SymfonyStyle
     */
    protected $io;

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
        $this->io = new SymfonyStyle($input, $output);
        $this->handle();

        return 0;
    }

    protected function getTarsFileRegistryServant(): string
    {
        $servantName = $this->getGatewayClient()->getConfig()->getTarsFileRegistryServantName();
        if (!$servantName) {
            throw new \InvalidArgumentException('tarsFileRegistryServantName 没有配置，请运行 configure 命令');
        }

        return $servantName;
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

    protected function removeTarsPackage(string $package): bool
    {
        $packages = $this->loadTarsPackages();
        if (isset($packages[$package])) {
            $packages[$package]->removeFiles();
            $config = json_decode(file_get_contents(self::CONFIG_FILE), true);
            unset($config['packages'][$package]);
            $serverName = $this->getGatewayClient()->call(
                [$this->getTarsFileRegistryServant(), 'getServerName'], $package
            );
            if (!empty($serverName) && isset($config['client']['servants'])) {
                foreach ($config['client']['servants'] as $id => $servant) {
                    if (0 === strpos($servant, $serverName.'.')) {
                        unset($config['client']['servants'][$id]);
                    }
                }
            }
            file_put_contents(
                self::CONFIG_FILE,
                json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            );

            return true;
        }

        return false;
    }

    protected function saveTarsPackage(TarsPackage $tarsPackage, string $path): void
    {
        $config = [];
        if (file_exists(self::CONFIG_FILE)) {
            $config = json_decode(file_get_contents(self::CONFIG_FILE), true);
        }
        if (isset($config['packages'][$tarsPackage->getName()])) {
            $tarsPackage->setFiles(array_values(array_unique(array_merge(
                $tarsPackage->getFiles(), $config['packages'][$tarsPackage->getName()]['files'] ?? []
            ))));
        }
        $config['packages'][$tarsPackage->getName()] = array_filter([
            'revision' => $tarsPackage->getRevision(),
            'files' => $tarsPackage->getFiles(),
            'path' => $tarsPackage->getPathPrefix(),
        ]);
        if (!isset($config['client'])) {
            $config['client'] = [];
        }
        if (!isset($config['client'][0])) {
            if (!isset($config['client']['tars_path']) && 'client' !== $path) {
                $config['client']['tars_path'] = $path;
            }
            $this->updateServants($config, $tarsPackage);
        }
        file_put_contents(
            self::CONFIG_FILE,
            json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    private function updateServants(array &$config, TarsPackage $tarsPackage): void
    {
        $serverName = $this->getGatewayClient()->call(
            [$this->getTarsFileRegistryServant(), 'getServerName'], $tarsPackage->getName()
        );
        if (empty($serverName)) {
            return;
        }
        $servants = $config['client']['servants'] ?? [];
        foreach ($tarsPackage->getServants($serverName) as $id => $servant) {
            [$module, $name] = explode('.', $id);
            if (!isset($servants[$name]) || $servants[$name] === $servant) {
                $servants[$name] = $servant;
            } else {
                $servants[$id] = $servant;
            }
        }
        $config['client']['servants'] = $servants;
    }

    abstract protected function handle(): void;
}
