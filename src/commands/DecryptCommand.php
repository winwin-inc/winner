<?php

namespace winwin\winner\commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Tag\TaggedValue;
use winwin\winner\VaultTagProcessor;
use winwin\winner\VaultTags;

class DecryptCommand extends AbstractCryptCommand
{
    protected function configure()
    {
        parent::configure();
        $this->setName('decrypt')
            ->setDescription('Decrypt cipher text in yaml file');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $key = $this->loadVaultKey();
        if ($input->getOption('string')) {
            $output->writeln($key->decrypt($input->getArgument('input')));
        } else {
            $format = $this->getFormat();
            $processor = new VaultTagProcessor(function (TaggedValue $value) use ($key, $format) {
                if (VaultTags::VAULT == $value->getTag()) {
                    $value = new TaggedValue(VaultTags::PASSWORD, $key->decrypt($value->getValue()));
                }

                return 'yaml' == $format ? $value : $value->getValue();
            });

            $result = $processor->process($this->getInputContent());
            $this->outputResult($result, $format);
        }
    }
}
