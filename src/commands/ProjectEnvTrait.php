<?php

declare(strict_types=1);

namespace winwin\winner\commands;

use Symfony\Component\Console\Output\OutputInterface;

trait ProjectEnvTrait
{
    protected function extractEnvVariables($project, OutputInterface $output)
    {
        $files = array_merge(
            glob("$project/config/*.php"),
            glob("$project/config/*/*.php"),
            glob("$project/vendor/*/*/config/*.php")
        );
        $vars = [];

        foreach ($files as $file) {
            if ($output->isVerbose()) {
                $output->writeln("Scan $file");
            }
            $relate_path = substr($file, strlen($project) + 1);
            $tokens = new \ArrayIterator(token_get_all(file_get_contents($file)));
            while ($tokens->valid()) {
                $token = $tokens->current();
                if (is_array($token) && T_STRING == $token[0] && in_array($token[1], ['getenv', 'env', 'secret'], true)) {
                    $function = $token[1];
                    $tokens->next();
                    $tokens->next();
                    $token = $tokens->current();
                    if (is_array($token) && T_CONSTANT_ENCAPSED_STRING == $token[0]) {
                        $vars[] = [
                            'name' => trim($token[1], "'\""),
                            'type' => in_array($function, ['secret'], true) ? 'vault' : 'plaintext',
                            'file' => $relate_path,
                            'line' => $token[2],
                        ];
                    }
                }
                $tokens->next();
            }
        }

        return $vars;
    }
}
