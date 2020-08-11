<?php

declare(strict_types=1);

namespace winwin\winner\commands;

use Symfony\Component\Console\Input\InputArgument;

class TarsUpdateCommand extends AbstractCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->setName('tars:update');
        $this->setDescription('更新 tars 定义文件');
        $this->addArgument('package', InputArgument::OPTIONAL, '更新包名');
    }

    protected function handle(): void
    {
        $name = $this->input->getArgument('package');
        foreach ($this->loadTarsPackages() as $package) {
            if (!empty($name) && $package->getName() !== $name) {
                continue;
            }
            foreach ($package->update($this->getGatewayClient()) as $file) {
                $this->output->writeln("<info>更新Tars定义文件 {$package->getName()} $file</info>");
            }
        }
        $this->output->writeln('<info>更新完成</info>');
    }
}
