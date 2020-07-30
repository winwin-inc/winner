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
        $this->setDescription('Add tars file to registry');
        $this->addOption('revision', null, InputOption::VALUE_REQUIRED, 'branch name of the package', 'master');
        $this->addArgument('package', InputArgument::REQUIRED, 'package to require');
    }

    protected function handle(): void
    {
        $package = $this->input->getArgument('package');
        $revision = $this->input->getOption('revision');
        $packages = $this->loadTarsPackages();
        $tarsPackage = $packages[$package] ?? new TarsPackage($package, $revision, []);
        $files = $this->getPackageFiles($package, $revision);
        if (empty($files)) {
            return;
        }
        $this->chooseFiles($tarsPackage, array_column($files, 'fileName'));
        $this->setPathPrefix($tarsPackage, $packages);
        $this->saveTarsPackage($tarsPackage);
        foreach ($tarsPackage->update($this->getGatewayClient()) as $file) {
            $this->output->writeln("<info>更新Tars定义文件 $file</info>");
        }
        $this->output->writeln("<info>添加{$tarsPackage->getName()}:{$tarsPackage->getRevision()} Tars定义文件</info>");
    }

    private function getPackageFiles(string $package, string $revision): array
    {
        $files = $this->getGatewayClient()->call($this->getTarsFileRegistryServant(), 'listFiles',
            $package, $revision);
        if (empty($files)) {
            $revisions = $this->getGatewayClient()->call($this->getTarsFileRegistryServant(), 'listRevisions', $package);
            if (empty($revisions)) {
                $this->output->writeln("<error>$package 没有Tars定义文件</error>");
            } else {
                $this->output->writeln(sprintf(
                    "<error>$package:$revision 没有 Tars 定义文件，使用 --revision 指定 %s 中的一个</error>", implode(',', $revisions)));
            }
        }

        return $files;
    }

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

    private function setPathPrefix(TarsPackage $tarsPackage, array $packages): void
    {
        if ($tarsPackage->getPathPrefix()) {
            return;
        }
        foreach ($packages as $otherPackage) {
            if ($otherPackage->getName() === $tarsPackage->getName()) {
                continue;
            }
            if (!empty($otherPackage->getPathPrefix())) {
                if ($otherPackage->getPathPrefix() === $tarsPackage->getPathPrefix()) {
                    throw new \InvalidArgumentException(sprintf('%s 和 %s 目录不能相同', $otherPackage->getPathPrefix(), $tarsPackage->getPathPrefix()));
                }
                continue;
            }
            $commonFiles = array_values(array_intersect($otherPackage->getFiles(), $tarsPackage->getFiles()));
            if (!$commonFiles) {
                continue;
            }
            $defaultPath = basename($tarsPackage->getName());
            $question = new Question(
                sprintf('%s 和 %s 冲突，请指定文件目录（默认 %s）：',
                    $commonFiles[0], $otherPackage->getName(), $defaultPath),
                $defaultPath
            );
            $answer = $this->getHelper('question')->ask($this->input, $this->output, $question);
            $tarsPackage->setPathPrefix($answer);

            return;
        }
    }
}
