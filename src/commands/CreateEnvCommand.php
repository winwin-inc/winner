<?php

declare(strict_types=1);

namespace winwin\winner\commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class CreateEnvCommand extends AbstractCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->setName('create-env');
        $this->setDescription('Create env file for application');
        $this->addOption('out', null, InputOption::VALUE_REQUIRED, 'Output file name');
        $this->addArgument('server', InputArgument::REQUIRED, 'Tars server name');
    }

    protected function handle(): void
    {
        [$app, $server] = explode('.', $this->input->getArgument('server'));

        $outFile = $this->input->getOption('out');
        $content = $this->getEnvFile($app, $server)."\n".$this->getDbPass($app, $server);
        if (null !== $outFile) {
            file_put_contents($outFile, $content);
        } else {
            $this->output->writeln($content);
        }
    }

    protected function getEnvFile(string $app, string $server): string
    {
        $ret = $this->getGatewayClient()->call(['tars.tarsconfig.ConfigObj', 'loadConfig'],
            $app, $server, 'env');

        return $ret['config'];
    }

    protected function getDbPass(string $app, string $server): string
    {
        [$app, $server] = explode('.', $this->input->getArgument('server'));
        $value = $this->getGatewayClient()->callWithHeaders(
            ['winwin.kms.KmsObj', 'getSecretValues'],
            [["$app/$server/db"]],
            ['x-tars-context' => "referer=$app.$server"]
        );
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

        return implode("\n", $lines);
    }
}
