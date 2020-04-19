<?php

namespace winwin\winner\commands;

use Ko\ProcessManager;
use Ko\SharedMemory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use winwin\winner\Linter;
use winwin\winner\reporter\TextReporter;

class LintCommand extends Command
{
    /**
     * @var array
     */
    private $config = [];

    /**
     * @var string
     */
    private $excludePattern;

    /**
     * @var int
     */
    private $failCount = 0;

    /**
     * @var int
     */
    private $passCount = 0;

    /**
     * @var int
     */
    private $fileCount = 0;

    protected function configure()
    {
        $this->setName('lint')
            ->setDescription('Lint php code file or directory')
            ->addArgument('dir', InputArgument::OPTIONAL, 'php code directory')
            ->addOption('php-version', 'p', InputOption::VALUE_REQUIRED, 'php version', '7.0')
            ->addOption('jobs', '-j', InputOption::VALUE_REQUIRED, 'Allow N jobs at once')
            ->addOption('exclude', '-e', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'directory to exclude')
            ->addOption('autoload', '-l', InputOption::VALUE_REQUIRED, 'autoload file');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->loadConfig();
        $this->addLoader($input);
        $this->registerClasses();

        $this->excludePattern = $this->buildExcludePattern($input);
        $dir = $input->getArgument('dir');
        if (!$dir) {
            $dir = '.';
        }
        if (is_file($dir)) {
            $this->lint($dir, $output);
        } elseif (is_dir($dir)) {
            $this->lintFilesInDir($dir, $output, $input->getOption('jobs'));
        } else {
            $output->writeln("<error>Cannot find source file from '{$dir}'</>");

            return -1;
        }

        if ($this->passCount === $this->fileCount) {
            $output->writeln("<info>{$this->fileCount} files passed</>");
        } else {
            $output->writeln(sprintf(
                '<info>%d files was scanned, %d passed, %d failed',
                $this->fileCount,
                $this->passCount,
                $this->failCount
            ));

            return -1;
        }

        return 0;
    }

    public function filter(/* @noinspection PhpUnusedParameterInspection */$current, $file, $it)
    {
        return !preg_match($this->excludePattern, $file)
            && is_readable($file)
            && (is_dir($file) || strpos($file, '.php') !== false);
    }

    public function lint($file, OutputInterface $output)
    {
        ++$this->fileCount;
        $reporter = $this->createLinter($file)
            ->lint()
            ->getReporter();
        /** @var TextReporter $reporter */
        if ($reporter->getErrors()) {
            $output->writeln('<error>'.$reporter.'</error>');
            ++$this->failCount;
        } else {
            ++$this->passCount;
        }
    }

    /**
     * @param OutputInterface $output
     * @param string          $dir
     */
    protected function lintFilesInDir($dir, OutputInterface $output, $jobs)
    {
        if ($jobs > 1) {
            $manager = new ProcessManager();
            if ($jobs > 16) {
                throw new \RuntimeException("Jobs should less than 16, got $jobs");
            }
            $mem = new SharedMemory();
            $processes = [];

            foreach (range(0, $jobs - 1) as $jobId) {
                $processes[] = $manager->fork(function () use ($dir, $output, $jobs, $jobId, $mem) {
                    $this->lintFilesInDirParrallel($dir, $output, $jobs, $jobId);
                    $mem['fail'] += $this->failCount;
                    $mem['pass'] += $this->passCount;
                    $mem['files'] += $this->fileCount;
                });
            }
            foreach ($processes as $process) {
                $process->wait();
            }
            $this->failCount = $mem['fail'];
            $this->passCount = $mem['pass'];
            $this->fileCount = $mem['files'];
        } else {
            $this->lintFilesInDirParrallel($dir, $output, 1, 0);
        }
    }

    private function lintFilesInDirParrallel($dir, OutputInterface $output, $jobs, $jobId)
    {
        $dir_it = new \RecursiveDirectoryIterator($dir);
        /** @noinspection PhpParamsInspection */
        $filter_it = new \RecursiveCallbackFilterIterator($dir_it, [$this, 'filter']);
        foreach (new \RecursiveIteratorIterator($filter_it) as $file => $fileinfo) {
            if (!is_file($file)) {
                continue;
            }
            if (crc32($file) % $jobs == $jobId) {
                if ($output->isVeryVerbose()) {
                    $output->writeln("<info>lint $file</>");
                }
                $this->lint($file, $output);
            }
        }
    }

    private function buildExcludePattern(InputInterface $input)
    {
        $exclude = $input->getOption('exclude') ?: ($this->config['exclude'] ?? []);
        if (!is_array($exclude)) {
            $exclude = [];
        }
        $exclude = array_unique(array_merge($exclude, ['.git', 'tests', 'vendor']));

        return '#('.implode('|', array_map('preg_quote', $exclude)).')$#';
    }

    private function createLinter($file)
    {
        $linter = new Linter($file, new TextReporter());
        if (!empty($this->config['ignoredClasses']) && is_array($this->config['ignoredClasses'])) {
            $linter->addIgnoredClasses($this->config['ignoredClasses']);
        }

        return $linter;
    }

    private function registerClasses()
    {
        if (PHP_MAJOR_VERSION < 7) {
            array_map([$this, 'registerClass'], [
                'Throwable', 'ReflectionType', 'ReflectionGenerator',
                'Error', 'TypeError', 'ParseError', 'AssertionError',
                'ArithmeticError', 'DivisionByZeroError',
            ]);
        }

        if (!empty($this->config['defined_classes'])) {
            array_map([$this, 'registerClass'], $this->config['defined_classes']);
        }
    }

    /**
     * @param InputInterface $input
     */
    protected function addLoader(InputInterface $input)
    {
        $autoload = $input->getOption('autoload');
        if ($autoload) {
            if (file_exists($autoload)) {
                include_once $autoload;
            } else {
                throw new \RuntimeException("autoload file '$autoload' does not exist");
            }
        } elseif (file_exists($file = realpath('vendor/autoload.php'))) {
            include_once $file;
        }
        if (!empty($this->config['autoload'])) {
            include_once $this->config['autoload'];
        }
    }

    protected function loadConfig()
    {
        if (file_exists('.phplint')) {
            $this->config = parse_ini_file('.phplint');
        }
    }

    private function registerClass($className)
    {
        if (class_exists($className) || interface_exists($className)) {
            return;
        }
        $pos = strrpos($className, '\\');
        if (false !== $pos) {
            $namespace = substr($className, 0, $pos - 1);
            $className = substr($className, $pos);
        }
        $code = 'class '.$className.'{}';
        eval(!empty($namespace) ? "namespace $namespace { $code }" : $code);
    }
}
