<?php

namespace Sage\Sail\Console;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'publish', description: 'Publish the Sage Sail Docker files into the project')]
class PublishCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $packagePath = dirname(__DIR__, 2);
        $replacements = [];

        foreach (['runtimes', 'database'] as $group) {
            foreach (glob($packagePath . '/' . $group . '/*', GLOB_ONLYDIR) ?: [] as $source) {
                $name = basename($source);

                $this->copyDirectory($source, $this->project->path('docker/' . $name));

                $replacements['./vendor/jeffersonrucu/sage-sail/' . $group . '/' . $name] = './docker/' . $name;
            }
        }

        $this->rewriteComposePaths($replacements);

        $this->io->success('Sage Sail Docker files published to the "docker" directory.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $replacements
     */
    private function rewriteComposePaths(array $replacements): void
    {
        $composePath = $this->project->composePath();

        if (! is_file($composePath)) {
            $this->io->warning('No compose file was found, so its build paths were left untouched.');

            return;
        }

        file_put_contents($composePath, str_replace(
            array_keys($replacements),
            array_values($replacements),
            (string) file_get_contents($composePath)
        ));
    }

    private function copyDirectory(string $source, string $destination): void
    {
        if (! is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($items as $item) {
            $target = $destination . DIRECTORY_SEPARATOR . $items->getSubPathname();

            if ($item->isDir()) {
                if (! is_dir($target)) {
                    mkdir($target, 0755, true);
                }

                continue;
            }

            copy($item->getPathname(), $target);

            // Entrypoint scripts must stay executable inside the container...
            chmod($target, $item->isExecutable() ? 0755 : 0644);
        }
    }
}
