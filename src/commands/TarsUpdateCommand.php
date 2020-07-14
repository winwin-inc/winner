<?php

declare(strict_types=1);

namespace winwin\winner\commands;

class TarsUpdateCommand extends AbstractCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->setName('tars:update');
        $this->setDescription('Update tars file from registry');
    }

    protected function handle(): void
    {
        foreach ($this->loadTarsPackages() as $package) {
            foreach ($package->update($this->getGatewayClient()) as $file) {
                $this->output->writeln("<info>更新Tars定义文件 {$package->getName()} $file</info>");
            }
        }
        $this->output->writeln('<info>更新完成</info>');
    }
}
