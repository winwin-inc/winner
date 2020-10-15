<?php

declare(strict_types=1);

namespace winwin\winner\commands;

use Laminas\Code\Generator\ValueGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExtractErrorsCommand extends Command
{
    protected function configure()
    {
        $this->setName('extract:errors');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dir = 'resources/translations/messages';
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \InvalidArgumentException("Cannot create directory $dir");
        }
        $file = $dir.'/zh_CN.php';
        $messages = [];
        if (is_file($file)) {
            $messages = require $file;
        }

        $found = false;
        foreach (glob('tars/servant/*.tars') as $tarsFile) {
            $content = file_get_contents($tarsFile);
            if (!preg_match('/enum\s+ErrorCode\s+\{(.*?)\};/ms', $content, $matches)) {
                continue;
            }
            $lines = explode("\n", $matches[1]);
            $desc = '';
            foreach ($lines as $line) {
                if (preg_match('#^\s*//(.*)#', $line, $matches)) {
                    $desc = trim($matches[1]);
                }
                if (false !== strpos($line, '=')) {
                    list($code, $value) = explode('=', $line, 2);
                    if (preg_match('/(\d+)/', $value, $matches)) {
                        $found = true;
                        $messages['errors'][$matches[1]] = $desc;
                    }
                }
            }
        }
        if ($found) {
            $generator = new ValueGenerator($messages, ValueGenerator::TYPE_ARRAY_SHORT);
            $generator->setIndentation('     '); // 2 spaces
            $code = $generator->generate();
            file_put_contents($file, "<?php\n\nreturn ".$code.";\n");
            $output->writeln('<info>成功更新错误码消息：</info>');
            $output->writeln((new ValueGenerator($messages['errors'], ValueGenerator::TYPE_ARRAY_SHORT))
                ->generate());
        } else {
            $output->write('<error>tars/servant 目录下没有找到错误码定义</error>');
        }

        return 0;
    }
}
