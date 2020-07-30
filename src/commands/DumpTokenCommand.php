<?php

declare(strict_types=1);

namespace winwin\winner\commands;

use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DumpTokenCommand extends Command
{
    protected function configure()
    {
        $this->setName('dump-token')
            ->setDescription('Dump php token')
            ->addArgument('file', InputArgument::REQUIRED, 'The file with php code');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $file = $input->getArgument('file');
        $content = file_get_contents('-' === $file ? 'php://stdin' : $file);
        if (false === $content) {
            throw new RuntimeException("Cannot read file '{$file}'");
        }
        $tokens = token_get_all($content);

        foreach ($tokens as $token) {
            if (is_array($token)) {
                echo implode("\t", [token_name($token[0]), json_encode($token[1]), $token[2]]), "\n";
            } else {
                echo json_encode($token), "\n";
            }
        }

        return 0;
    }
}
