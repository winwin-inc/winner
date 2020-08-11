<?php

declare(strict_types=1);

namespace winwin\winner\commands;

use Composer\Semver\Comparator;
use Symfony\Component\Console\Helper\Table;
use winwin\winner\TarsPackage;

class TarsOutdatedCommand extends AbstractCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->setName('tars:outdated');
        $this->setDescription('列出需要更新的 tars 定义文件');
    }

    protected function handle(): void
    {
        $table = new Table($this->output);
        $table->setHeaders(['包名', '使用版本号', '当前版本号', '更新文件']);
        foreach ($this->loadTarsPackages() as $package) {
            $this->listOutdated($package, $table);
        }
        $table->render();
    }

    private function listOutdated(TarsPackage $package, Table $table): void
    {
        $revisions = $this->getGatewayClient()->call(
            [$this->getTarsFileRegistryServant(), 'listRevisions'],
            $package->getName()
        );
        foreach ($revisions as $revision) {
            if (Comparator::greaterThan($revision, $package->getRevision())) {
                $table->addRow([
                    $package->getName(),
                    $package->getRevision(),
                    "<comment>$revision</comment>",
                    implode(',', $package->getUpdatedFiles($this->getGatewayClient(), $revision)),
                ]);

                return;
            }
        }
        $updatedFiles = $package->getUpdatedFiles($this->getGatewayClient(), $package->getRevision());
        if (!empty($updatedFiles)) {
            $table->addRow([
                $package->getName(),
                $package->getRevision(),
                sprintf('<error>%s</error>', $package->getRevision()),
                implode(',', $updatedFiles),
            ]);
        }
    }
}
