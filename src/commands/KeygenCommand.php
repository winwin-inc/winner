<?php

namespace winwin\winner\commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use winwin\support\crypto\Key;

class KeygenCommand extends Command
{
    protected function configure()
    {
        $this->setName('keygen')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Key format: defuse or winwin')
            ->setDescription('Generate a random encryption key');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $key = Key::createNewRandomKey();

        if ('defuse' == $input->getOption('format')) {
            $key = $key->getDefuseKey();
        }
        echo $key->saveToAsciiSafeString(), "\n";
    }
}
