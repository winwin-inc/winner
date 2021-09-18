<?php

declare(strict_types=1);

namespace winwin\winner\commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Finder\Finder;
use winwin\winner\kuiper\FixConfigPhpVisitor;
use winwin\winner\kuiper\FixEntityVisitor;
use winwin\winner\kuiper\FixIndexPhpVisitor;

class KuiperUpgradeCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('kuiper:upgrade');
        $this->setDescription('升级 kuiper 版本到 0.6');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var QuestionHelper $question */
        $question = $this->getHelper('question');
        $answer = $question->ask($input, $output, new ConfirmationQuestion('将修改目录中的文件，请确保项目文件已经提交到'
            .' git 中，如果出现错误使用 `git checkout --` 恢复代码。[Yn]'));
        if (!$answer) {
            return 0;
        }

        $this->updateIndexPhp();
        $this->fixEntityDateTime();
        $config = $this->updateConfig();
        $this->updateComposerJson($config);
        passthru('composer container');
        passthru('composer update');
        system('rm -f .tars-gen.cache');
        passthru('composer gen');

        return 0;
    }

    private function updateComposerJson(FixConfigPhpVisitor $config): void
    {
        if (!file_exists('composer.json')) {
            throw new \InvalidArgumentException('当前目录下没有 composer.json，确保在项目根目录下运行命令');
        }
        $composerJson = json_decode(file_get_contents('composer.json'), true);

        $composerJson['require'] = $this->fixRequirement($config, $composerJson['require'] ?? []);
        $composerJson['require-dev'] = $this->fixRequirementDev($composerJson['require-dev'] ?? []);
        $composerJson['extra']['kuiper'] = $this->fixKuiperConfig($config, $composerJson['extra']['kuiper']);
        $composerJson['scripts']['package'] = 'kuiper\\tars\\server\\PackageBuilder::run';
        file_put_contents(
            'composer.json',
            json_encode($composerJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT).PHP_EOL
        );
    }

    private function updateIndexPhp(): void
    {
        if (!file_exists('src/index.php')) {
            throw new \InvalidArgumentException('当前目录下没有 src/index.php，确保在项目根目录下运行命令');
        }
        file_put_contents('src/index.php', FixIndexPhpVisitor::fix('src/index.php'));
    }

    private function updateConfig(): FixConfigPhpVisitor
    {
        $config = FixConfigPhpVisitor::fix('src/config.php');
        file_put_contents('src/config.php', $config->getCode());

        return $config;
    }

    private function fixRequirement(FixConfigPhpVisitor $config, array $deps): array
    {
        unset($deps['kuiper/kuiper'], $deps['wenbinye/tars']);
        if (isset($deps['php'])) {
            $deps['php'] = '>=7.2.5';
        }
        if (isset($deps['winwin/support'])) {
            $deps['winwin/support'] = '^0.5';
        } else {
            $deps['kuiper/tars'] = '^0.6';
        }
        foreach ([
                     'winwin/job-queue' => '^0.5',
                     'winwin/ddd' => '^0.2',
                     'winwin/admin-support' => '^0.2',
                     'winwin/file-system' => '^0.3',
                 ] as $pkg => $version) {
            if (isset($deps[$pkg])) {
                $deps[$pkg] = $version;
            }
        }
        if ($config->hasConfig('application.database')) {
            $deps['kuiper/db'] = '^0.6';
        }
        if ($config->hasConfig('application.web')) {
            $deps['kuiper/web'] = '^0.6';
        }

        return $deps;
    }

    private function fixRequirementDev(array $deps): array
    {
        if (isset($deps['wenbinye/tars-gen'])) {
            $deps['wenbinye/tars-gen'] = '^0.4';
        }

        return $deps;
    }

    private function fixKuiperConfig(FixConfigPhpVisitor $config, array $meta): array
    {
        if (!in_array('kuiper\\tars\\config\\TarsServerConfiguration', $meta['configuration'] ?? [], true)) {
            $meta['configuration'][] = 'kuiper\\tars\\config\\TarsServerConfiguration';
        }
        if ($config->hasConfig('application.web')
            && !in_array('kuiper\\web\\WebConfiguration', $meta['configuration'] ?? [], true)) {
            $meta['configuration'][] = 'kuiper\\web\\WebConfiguration';
        }
        $meta['whitelist'] = ['kuiper/*', 'winwin/*'];

        return $meta;
    }

    private function fixEntityDateTime(): void
    {
        foreach (Finder::create()
            ->in(['src/domain', 'src/application/model'])
            ->name('*.php')
            ->files() as $entityFile) {
            $entityFile = $entityFile->getRealPath();
            echo $entityFile, "\n";
            $visitor = FixEntityVisitor::fix($entityFile);
            if ($visitor->isFixed()) {
                file_put_contents($entityFile, $visitor->getCode());
            }
        }
    }
}
