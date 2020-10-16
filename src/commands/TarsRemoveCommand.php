<?php

declare(strict_types=1);

namespace winwin\winner\commands;

use Symfony\Component\Console\Input\InputArgument;

class TarsRemoveCommand extends AbstractCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->setName('tars:remove');
        $this->setDescription('移除依赖');
        $this->addArgument('package', InputArgument::REQUIRED, '包名');
    }

    protected function handle(): void
    {
        $package = $this->input->getArgument('package');
        $ret = $this->removeTarsPackage($package);
        if ($ret) {
            $this->output->writeln("<info>Remove package $package successfully</info> ");
        } else {
            $this->output->writeln("<error>Package $package not found</error> ");
        }
    }
}
