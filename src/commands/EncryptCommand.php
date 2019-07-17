<?php

namespace winwin\winner\commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Tag\TaggedValue;
use winwin\winner\VaultTagProcessor;
use winwin\winner\VaultTags;

class EncryptCommand extends AbstractCryptCommand
{
    protected function configure()
    {
        parent::configure();
        $this->setName('encrypt')
            ->setDescription('Encrypt password value in yaml file');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $key = $this->loadVaultKey();
        if ($input->getOption('string')) {
            $output->writeln($key->encrypt($input->getArgument('input')));
        } else {
            $format = $this->getFormat();
            $processor = new VaultTagProcessor(function (TaggedValue $value) use ($key, $format) {
                if (VaultTags::PASSWORD == $value->getTag()) {
                    $value = new TaggedValue(VaultTags::VAULT, $key->encrypt($value->getValue()));
                }

                return 'yaml' == $format ? $value : $value->getValue();
            });

            $result = $processor->process($this->getInputContent());
            $this->outputResult($result, $format);
        }
    }
}
