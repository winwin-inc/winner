<?php

declare(strict_types=1);

namespace winwin\winner\commands;

use Symfony\Component\Console\Input\InputOption;

class TarsPublishCommand extends AbstractCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->setName('tars:publish');
        $this->addOption('revision', null, InputOption::VALUE_REQUIRED, "file revision");
        $this->setDescription('Add tars file to registry');
    }

    protected function handle(): void
    {
        $projectPath = getcwd();
        if (!file_exists($projectPath.'/composer.json')) {
            $this->output->writeln('<error>composer.json not found in current directory</error>');
        }
        $composerJson = json_decode(file_get_contents($projectPath.'/composer.json'), true);
        if (!isset($composerJson['name'])) {
            throw new \InvalidArgumentException('There is no package name in composer.json');
        }
        $package = $composerJson['name'];
        $revision = $this->input->getOption('revision')
            ?? exec("git branch | grep '*' | awk '{print $2}'");
        $response = $this->getGatewayClient()->call($this->getTarsFileRegistryServant(), 'listFiles',
            $package, $revision);
        $existFiles = [];
        foreach ($response as $tarsFile) {
            $existFiles[$tarsFile['fileName']] = $tarsFile;
        }

        foreach (glob($projectPath.'/tars/servant/*.tars') as $tarsFile) {
            $fileName = basename($tarsFile);
            if (isset($existFiles[$fileName])) {
                $md5 = md5_file($tarsFile);
                if ($existFiles[$fileName]['md5'] === $md5) {
                    $this->output->writeln("<info>$fileName 未修改</info>");
                    continue;
                }
            }
            $version = $this->getGatewayClient()->call($this->getTarsFileRegistryServant(), 'add', [
                'packageName' => $package,
                'revision' => $revision,
                'fileName' => $fileName,
                'content' => file_get_contents($tarsFile),
            ]);
            $this->output->writeln("<info>成功上传 {$fileName}，版本号 $version</info>");
        }
        $this->output->writeln("<info>成功上传 $package:$revision Tars定义文件</info>");
    }
}
