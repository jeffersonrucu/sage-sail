<?php

namespace Sage\Sail\Console;

use Sage\Sail\Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'sage-sail:install')]
class InstallCommand extends Command
{
    use Concerns\InteractsWithDockerComposeServices;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sage-sail:install
                {--with= : The services that should be included in the installation}
                {--devcontainer : Create a .devcontainer configuration directory}
                {--php=8.4 : The PHP version that should be used}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install Sage Sail\'s default Docker Compose file';

    /**
     * Execute the console command.
     *
     * @return int|null
     */
    public function handle()
    {
        if ($this->option('with')) {
            $services = $this->option('with') == 'none' ? [] : explode(',', $this->option('with'));
        } elseif ($this->option('no-interaction')) {
            $services = $this->defaultServices;
        } else {
            $services = $this->gatherServicesInteractively();
        }

        if ($invalidServices = array_diff($services, $this->services)) {
            $this->components->error('Invalid services ['.implode(',', $invalidServices).'].');

            return 1;
        }

        $this->buildDockerCompose($services);
        $this->replaceEnvVariables($services);
        $this->configurePhpUnit();

        if ($this->option('devcontainer')) {
            $this->installDevContainer();
        }

        $this->prepareInstallation($services);

        $this->output->writeln('');
        $this->components->info('Sage Sail scaffolding installed successfully. You may run your Docker containers using Sage Sail\'s "up" command.');

        $this->output->writeln('<fg=gray>➜</> <options=bold>./vendor/bin/sage-sail up</>');

        if (in_array('mysql', $services) ||
            in_array('mariadb', $services) ||
            in_array('pgsql', $services)) {
            // $this->components->warn('A database service was installed. Run "artisan migrate" to prepare your database:');

            // $this->output->writeln('<fg=gray>➜</> <options=bold>./vendor/bin/sage sail artisan migrate</>');
        }

        $this->output->writeln('');
    }
}
