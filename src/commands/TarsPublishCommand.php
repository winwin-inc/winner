<?php

declare(strict_types=1);

namespace winwin\winner\commands;

use Symfony\Component\Console\Input\InputOption;
use winwin\winner\TarsPackage;

class TarsPublishCommand extends AbstractCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->setName('tars:publish');
        $this->addOption('server', null, InputOption::VALUE_REQUIRED, '服务名');
        $this->addOption('revision', null, InputOption::VALUE_REQUIRED, '文件版本号');
        $this->setDescription('发布 tars 定义文件');
    }

    protected function handle(): void
    {
        $projectPath = getcwd();
        if (!file_exists($projectPath.'/composer.json')) {
            $this->io->error('composer.json not found in current directory');
        }
        $composerJson = json_decode(file_get_contents($projectPath.'/composer.json'), true);
        if (!isset($composerJson['name'])) {
            throw new \InvalidArgumentException('There is no package name in composer.json');
        }
        $package = $composerJson['name'];
        $serverName = $this->input->getOption('server');
        if (empty($serverName)) {
            $serverName = $this->getGatewayClient()->call([$this->getTarsFileRegistryServant(), 'getServerName'], $package);
            if (empty($serverName)) {
                $serverName = $this->io->ask("What is server name for $package: ");
            }
        }

        if (!empty($serverName)) {
            $this->getGatewayClient()->call([$this->getTarsFileRegistryServant(), 'setServerName'], $package, $serverName);
        }

        $revision = $this->input->getOption('revision')
            ?? exec("git branch | grep '*' | awk '{print $2}'");
        $response = $this->getGatewayClient()->call(
            [$this->getTarsFileRegistryServant(), 'listFiles'],
            $package, $revision
        );
        $existFiles = [];
        foreach ($response as $tarsFile) {
            $existFiles[$tarsFile['fileName']] = $tarsFile;
        }

        foreach (glob($projectPath.'/'.TarsPackage::TARS_FILE_PATH.'/servant/*.tars') as $tarsFile) {
            $fileName = basename($tarsFile);
            if ('EventBusSubscriber.tars' === $fileName) {
                continue;
            }
            $existFile = $existFiles[$fileName] ?? null;
            if (isset($existFile)) {
                unset($existFiles[$fileName]);
                $md5 = md5_file($tarsFile);
                if ($existFile['md5'] === $md5) {
                    $this->output->writeln("<info>$fileName 未修改</info>");
                    continue;
                }
            }
            $version = $this->getGatewayClient()->call(
                [$this->getTarsFileRegistryServant(), 'add'],
                [
                    'packageName' => $package,
                    'revision' => $revision,
                    'fileName' => $fileName,
                    'content' => file_get_contents($tarsFile),
                ]
            );
            $this->io->note("成功上传 {$fileName}，版本号 $version");
        }
        foreach (array_keys($existFiles) as $fileName) {
            $this->getGatewayClient()->call(
                [$this->getTarsFileRegistryServant(), 'deleteFile'],
                $package, $revision, $fileName
            );
        }
        $this->io->success("成功上传 $package:$revision Tars定义文件");
    }
}
