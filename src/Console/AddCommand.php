<?php

namespace Sage\Sail\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'add', description: 'Add a service to an existing Sage Sail installation')]
class AddCommand extends Command
{
    use Concerns\InteractsWithDockerComposeServices;

    protected function configure(): void
    {
        $this
            ->addArgument('services', InputArgument::OPTIONAL, 'The services that should be added')
            ->addOption('no-build', null, InputOption::VALUE_NONE, 'Skip pulling and building the Docker images');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $services = $this->resolveServices($input);

        if ($invalid = array_diff($services, $this->services)) {
            $this->io->error('Invalid services [' . implode(', ', $invalid) . '].');

            return self::FAILURE;
        }

        $this->buildDockerCompose($services, $this->installedPhpVersion());
        $this->configureEnvironment($services);
        if (! $input->getOption('no-build')) {
            $this->prepareInstallation($services);
        }

        $this->io->success('Additional Sage Sail services installed successfully.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function resolveServices(InputInterface $input): array
    {
        $services = $input->getArgument('services');

        if ($services !== null) {
            return $services === 'none' ? [] : explode(',', $services);
        }

        return $input->isInteractive() ? $this->gatherServicesInteractively() : $this->defaultServices;
    }

    /**
     * Read the PHP version already referenced by the compose file so it is not rewritten.
     */
    private function installedPhpVersion(): string
    {
        $composePath = $this->project->composePath();

        if (is_file($composePath) &&
            preg_match('#runtimes/(\d+\.\d+)#', (string) file_get_contents($composePath), $matches) === 1) {
            return $matches[1];
        }

        return '8.4';
    }
}
