<?php

declare(strict_types=1);

namespace winwin\winner\commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\Question;
use winwin\winner\TarsPackage;

class TarsRequireCommand extends AbstractCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->setName('tars:require');
        $this->setDescription('下载 Tars 定义文件');
        $this->addOption('revision', null, InputOption::VALUE_REQUIRED, '包分支名或版本号', 'master');
        $this->addOption('path', null, InputOption::VALUE_REQUIRED, '本地安装路径');
        $this->addArgument('package', InputArgument::REQUIRED, '包名');
    }

    protected function handle(): void
    {
        $package = $this->input->getArgument('package');
        $revision = $this->input->getOption('revision');
        $path = $this->input->getOption('path');

        if (!is_dir(TarsPackage::TARS_FILE_PATH)
            && !mkdir(TarsPackage::TARS_FILE_PATH)
            && !is_dir(TarsPackage::TARS_FILE_PATH)) {
            throw new \InvalidArgumentException('Cannot create directory '.TarsPackage::TARS_FILE_PATH);
        }
        if (empty($path)
            && (is_dir(TarsPackage::TARS_FILE_PATH.'/servant')
                || is_dir(TarsPackage::TARS_FILE_PATH.'/client'))) {
            $path = 'client';
        }
        $packages = $this->loadTarsPackages();
        $tarsPackage = $packages[$package] ?? new TarsPackage($package, $revision, []);
        $files = $this->getPackageFiles($package, $revision);
        if (empty($files)) {
            return;
        }
        $this->chooseFiles($tarsPackage, array_column($files, 'fileName'));
        $this->setPathPrefix($tarsPackage, $packages, $path);
        foreach ($tarsPackage->update($this->getGatewayClient()) as $file) {
            $this->output->writeln("<info>更新Tars定义文件 $file</info>");
        }
        $this->saveTarsPackage($tarsPackage);
        $this->output->writeln("<info>添加{$tarsPackage->getName()}:{$tarsPackage->getRevision()} Tars定义文件</info>");
    }

    /**
     * 获取包 tars 定义文件列表.
     *
     * @return string[]
     */
    private function getPackageFiles(string $package, string $revision): array
    {
        $files = $this->getGatewayClient()->call(
            [$this->getTarsFileRegistryServant(), 'listFiles'],
            $package, $revision
        );
        if (empty($files)) {
            $revisions = $this->getGatewayClient()->call(
                [$this->getTarsFileRegistryServant(), 'listRevisions'],
                $package
            );
            if (empty($revisions)) {
                $this->output->writeln("<error>$package 没有Tars定义文件</error>");
            } else {
                $this->output->writeln(sprintf(
                    "<error>$package:$revision 没有 Tars 定义文件，使用 --revision 指定 %s 中的一个</error>", implode(',', $revisions)));
            }
        }

        return $files;
    }

    /**
     * 交互询问需要引入的 tars 定义文件.
     *
     * @param string[] $fileNames
     */
    private function chooseFiles(TarsPackage $tarsPackage, array $fileNames): void
    {
        if ($tarsPackage->getFiles() == $fileNames) {
            return;
        }

        if (1 === count($fileNames)) {
            $tarsPackage->setFiles($fileNames);

            return;
        }

        $allFileNames = array_merge($fileNames, ['全部']);
        $lastOne = count($allFileNames);
        $choices = array_map(static function ($index, $fileName) {
            return " $index: $fileName";
        }, range(1, $lastOne), $allFileNames);
        $question = new Question("选择添加的定义文件：\n".implode("\n", $choices)
            ."\n输入序号（空格分隔，默认选择全部）：", $lastOne);
        $answer = $this->getHelper('question')->ask($this->input, $this->output, $question);
        $select = array_map('intval', preg_split("/\s+/", trim($answer)));
        if (in_array($lastOne, $select, true)) {
            $tarsPackage->setFiles($fileNames);
        } else {
            $tarsPackage->setFiles(array_unique(array_filter(array_map(static function ($index) use ($fileNames) {
                return $fileNames[$index - 1] ?? null;
            }, $select))));
        }
    }

    /**
     * @param TarsPackage[] $packages
     */
    private function setPathPrefix(TarsPackage $package, array $packages, ?string $prefix): void
    {
        if (!empty($prefix) && empty($package->getPathPrefix())) {
            $package->setPathPrefix($prefix);
        }
        foreach ($packages as $otherPackage) {
            if ($otherPackage->getName() === $package->getName()) {
                continue;
            }
            $commonFiles = array_values(array_intersect($otherPackage->getFullNames(), $package->getFullNames()));
            if (!$commonFiles) {
                continue;
            }

            $defaultPath = (empty($package->getPathPrefix()) ? $package->getPathPrefix().'/' : '')
                .basename($package->getName());
            $question = new Question(
                sprintf('%s 和 %s 冲突，请指定文件目录（默认 %s）：',
                    $commonFiles[0], $otherPackage->getName(), $defaultPath),
                $defaultPath
            );
            $answer = $this->getHelper('question')->ask($this->input, $this->output, $question);
            $package->setPathPrefix($answer);

            return;
        }
    }
}
