<?php

namespace Sage\Sail\Console;

use Sage\Sail\Environment;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'install', description: 'Install the Sage Sail Docker Compose file')]
class InstallCommand extends Command
{
    use Concerns\InteractsWithDockerComposeServices;

    /**
     * The WordPress secrets Bedrock expects to find in the environment.
     *
     * @var array<int, string>
     */
    private const SALTS = [
        'AUTH_KEY',
        'SECURE_AUTH_KEY',
        'LOGGED_IN_KEY',
        'NONCE_KEY',
        'AUTH_SALT',
        'SECURE_AUTH_SALT',
        'LOGGED_IN_SALT',
        'NONCE_SALT',
    ];

    protected function configure(): void
    {
        $this
            ->addOption('with', null, InputOption::VALUE_REQUIRED, 'The services that should be included in the installation')
            ->addOption('php', null, InputOption::VALUE_REQUIRED, 'The PHP version that should be used', '8.4')
            ->addOption('devcontainer', null, InputOption::VALUE_NONE, 'Create a .devcontainer configuration directory')
            ->addOption('no-build', null, InputOption::VALUE_NONE, 'Skip pulling and building the Docker images');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (! $this->project->isBedrock()) {
            $this->io->warning('This does not look like a Bedrock project. Run this command from the Bedrock root directory.');
        }

        $services = $this->resolveServices($input);

        if ($invalid = array_diff($services, $this->services)) {
            $this->io->error('Invalid services [' . implode(', ', $invalid) . '].');

            return self::FAILURE;
        }

        $this->buildDockerCompose($services, $input->getOption('php'));
        $this->configureEnvironment($services);
        $this->configureWordPress();

        if ($input->getOption('devcontainer')) {
            $this->installDevContainer();
        }

        if (! $input->getOption('no-build')) {
            $this->prepareInstallation($services);
        }

        $this->io->success('Sage Sail scaffolding installed successfully.');
        $this->io->writeln('  Start your containers with: <options=bold>./vendor/bin/sage-sail up -d</>');
        $this->io->writeln('  Then install WordPress with: <options=bold>./vendor/bin/sage-sail wp core install --help</>');
        $this->io->newLine();

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function resolveServices(InputInterface $input): array
    {
        $with = $input->getOption('with');

        if ($with !== null) {
            return $with === 'none' ? [] : explode(',', $with);
        }

        return $input->isInteractive() ? $this->gatherServicesInteractively() : $this->defaultServices;
    }

    /**
     * Set the WordPress URLs and generate any missing secrets.
     */
    private function configureWordPress(): void
    {
        $envPath = $this->project->envPath();

        if ($envPath === null) {
            return;
        }

        $env = new Environment($envPath);

        $env->set('WP_ENV', 'development');
        $env->set('WP_HOME', 'http://localhost');
        $env->set('WP_SITEURL', '${WP_HOME}/wp');

        foreach (self::SALTS as $salt) {
            $current = $env->get($salt);

            // Bedrock ships these as the "generateme" placeholder...
            if ($current === null || $current === '' || $current === 'generateme') {
                $env->set($salt, $this->generateSalt());
            }
        }

        $env->save();
    }

    /**
     * Quote characters are excluded so the value stays safe to embed in a dotenv file.
     */
    private function generateSalt(): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#%^&*()-_=+[]{}<>.,:;/|~';
        $salt = '';

        for ($i = 0; $i < 64; $i++) {
            $salt .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $salt;
    }
}
