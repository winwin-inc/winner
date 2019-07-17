<?php

namespace winwin\winner\commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use winwin\support\crypto\Key;

abstract class AbstractCryptCommand extends Command
{
    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface
     */
    protected $output;

    protected function configure()
    {
        $this
            ->addOption('string', 's', InputOption::VALUE_NONE, 'Input string')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'output file name, default output to stdout')
            ->addOption('vault-key', 'k', InputOption::VALUE_REQUIRED, 'Vault encryption key')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format, support yaml,json, env', 'yaml')
            ->addArgument('input', InputArgument::REQUIRED, 'input file name');
    }

    protected function getFormat()
    {
        $format = $this->input->getOption('format');
        if (!in_array($format, ['yaml', 'json', 'env'])) {
            throw new \InvalidArgumentException("Unknown output format '$format'");
        }

        return $format;
    }

    protected function outputResult($result, $format)
    {
        $outputFile = $this->input->getOption('output');
        if (!$outputFile) {
            $outputFile = 'php://stdout';
        }

        if ('yaml' == $format) {
            $content = Yaml::dump($result, 2, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
        } elseif ('json' == $format) {
            $content = json_encode($result, JSON_PRETTY_PRINT);
        } elseif ('env' == $format) {
            $content = '';
            foreach ($result as $key => $value) {
                $content .= $key.'='.json_encode($value, JSON_UNESCAPED_SLASHES)."\n";
            }
        } else {
            throw new \InvalidArgumentException('Unknown output format');
        }
        file_put_contents($outputFile, $content);
        if ($this->output->isVerbose() && 'php://stdout' !== $outputFile) {
            $this->output->writeln("<info>Write to file $outputFile</info>");
        }
    }

    protected function getInputContent()
    {
        $file = $this->input->getArgument('input');
        if ('-' == $file) {
            $file = 'php://stdin';
        } elseif (!is_readable($file)) {
            throw new \RuntimeException("Cannot read file '$file'");
        }

        return file_get_contents($file);
    }

    protected function loadVaultKey()
    {
        $vaultKey = $this->input->getOption('vault-key');

        if ($vaultKey) {
            return Key::load($vaultKey);
        } else {
            return Key::getInstance();
        }
    }
}
