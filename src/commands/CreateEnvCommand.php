<?php

declare(strict_types=1);

namespace winwin\winner\commands;

use kuiper\swoole\Application;
use kuiper\tars\integration\ConfigServant;
use kuiper\tars\server\ServerProperties;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use winwin\winner\integration\kms\KmsServant;

class CreateEnvCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('create-env');
        $this->setDescription('Create env file for application');
        $this->addOption('registry', null, InputOption::VALUE_REQUIRED, 'Tars Registry');
        $this->addOption('out', null, InputOption::VALUE_REQUIRED, 'Output file name');
        $this->addArgument('server', InputArgument::REQUIRED, 'Tars server name');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        [$app, $server] = explode('.', $input->getArgument('server'));
        [$host, $port] = explode(':', $input->getOption('registry') ?? '127.0.0.1:17890');
        $_SERVER['argv'] = array_merge(['--define', 'php_config_file=winner.php'], $_SERVER['argv']);
        $application = Application::create();
        Application::getInstance()->getConfig()->merge([
            'application' => [
                'tars' => [
                    'client' => [
                        'locator' => "tars.tarsregistry.QueryObj@tcp -h $host -p $port",
                    ],
                ],
            ],
        ]);
        $container = $application->getContainer();
        $serverProperties = new ServerProperties();
        $serverProperties->setApp($app);
        $serverProperties->setServer($server);
        $container->set(ServerProperties::class, $serverProperties);
        $configServant = $container->get(ConfigServant::class);
        $configServant->loadConfig($app, $server, 'env', $content);
        $kms = $container->get(KmsServant::class);
        $value = $kms->getSecretValues(["$app/$server/db"]);
        // print_r([$content, $value]);
        $lines = [];
        foreach ($value as $name => $secret) {
            if (empty($secret)) {
                continue;
            }
            $parts = explode('/', $name);
            parse_str($secret, $secretPair);
            foreach ($secretPair as $key => $val) {
                $lines[] = strtoupper(end($parts).'_'.$key).'='.$val;
            }
        }
        $outFile = $input->getOption('out');
        $content .= "\n".implode("\n", $lines);
        if (null !== $outFile) {
            file_put_contents($outFile, $content);
        } else {
            $output->writeln($content);
        }

        return 0;
    }
}
