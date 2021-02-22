<?php

declare(strict_types=1);

namespace winwin\winner\commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ChoiceQuestion;
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
        if (empty($path)) {
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
            $this->io->note("更新Tars定义文件 $file");
        }
        $this->saveTarsPackage($tarsPackage, $path);
        $this->io->success("添加{$tarsPackage->getName()}:{$tarsPackage->getRevision()} Tars定义文件");
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
                $this->io->error("$package 没有Tars定义文件");
            } else {
                $this->io->error(sprintf(
                    "$package:$revision 没有 Tars 定义文件，使用 --revision 指定 %s 中的一个", implode(',', $revisions)));
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

        $all = '全部';
        $question = new ChoiceQuestion('选择添加的定义文件', array_merge([$all], $fileNames));
        $question->setMultiselect(true);
        $answer = $this->io->askQuestion($question);
        if (in_array($all, $answer, true)) {
            $tarsPackage->setFiles($fileNames);
        } else {
            $tarsPackage->setFiles($answer);
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
            $answer = $this->io->ask(sprintf('%s 和 %s 冲突，请指定文件目录（默认 %s）：',
                $commonFiles[0], $otherPackage->getName(), $defaultPath),
                $defaultPath);
            $package->setPathPrefix($answer);

            return;
        }
    }
}
