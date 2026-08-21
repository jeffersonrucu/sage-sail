<?php

namespace Sage\Sail\Console;

use Sage\Sail\Project;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

abstract class Command extends BaseCommand
{
    protected Project $project;

    protected SymfonyStyle $io;

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->project = Project::fromWorkingDirectory();
        $this->io = new SymfonyStyle($input, $output);
    }

    /**
     * Run the given shell commands, streaming their output.
     *
     * @param  array<int, string>  $commands
     */
    protected function runCommands(array $commands): int
    {
        $process = Process::fromShellCommandline(implode(' && ', $commands), $this->project->path(), null, null, null);

        if (Process::isTtySupported()) {
            try {
                $process->setTty(true);
            } catch (\RuntimeException $e) {
                $this->io->warning($e->getMessage());
            }
        }

        return $process->run(fn ($type, $line) => $this->io->write('    ' . $line));
    }
}
