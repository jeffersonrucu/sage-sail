<?php

namespace Sage\Sail\Console;

use Sage\Sail\Environment;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\text;

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

    /**
     * @var array{0: string, 1: string}|null
     */
    private ?array $administrator = null;

    private ?string $theme = null;

    protected function configure(): void
    {
        $this
            ->addOption('with', null, InputOption::VALUE_REQUIRED, 'The services that should be included in the installation')
            ->addOption('php', null, InputOption::VALUE_REQUIRED, 'The PHP version that should be used', '8.4')
            ->addOption('devcontainer', null, InputOption::VALUE_NONE, 'Create a .devcontainer configuration directory')
            ->addOption('no-build', null, InputOption::VALUE_NONE, 'Skip pulling and building the Docker images')
            ->addOption('no-start', null, InputOption::VALUE_NONE, 'Skip starting the containers and installing WordPress')
            ->addOption('title', null, InputOption::VALUE_REQUIRED, 'The WordPress site title')
            ->addOption('admin-user', null, InputOption::VALUE_REQUIRED, 'The WordPress administrator username', 'admin')
            ->addOption('admin-password', null, InputOption::VALUE_REQUIRED, 'The WordPress administrator password', 'password')
            ->addOption('admin-email', null, InputOption::VALUE_REQUIRED, 'The WordPress administrator email address', 'admin@example.com')
            ->addOption('theme', null, InputOption::VALUE_REQUIRED, 'Name of the Sage theme to install and activate');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Anything else would produce a compose file that cannot serve the site...
        if (! $this->project->isBedrock()) {
            $this->io->error([
                'Sage Sail requires a Bedrock project.',
                sprintf('No "web" directory and "config/application.php" were found in %s.', $this->project->path()),
            ]);

            return self::FAILURE;
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

        if ($input->getOption('no-start')) {
            $this->io->success('Sage Sail scaffolding installed successfully.');
            $this->io->writeln('  Start your containers with: <options=bold>./vendor/bin/sage-sail up -d</>');
            $this->io->newLine();

            return self::SUCCESS;
        }

        return $this->startContainers($input, $services);
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
        $env->set('WP_HOME', $this->siteUrl($env));
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
     * WordPress redirects using this URL, so it has to carry the published port.
     */
    private function siteUrl(Environment $env): string
    {
        $port = $env->get('APP_PORT');

        return $port === null || $port === '' || $port === '80'
            ? 'http://localhost'
            : 'http://localhost:' . $port;
    }

    /**
     * @param  array<int, string>  $services
     */
    private function startContainers(InputInterface $input, array $services): int
    {
        if ($this->runCommands(['./vendor/bin/sage-sail up -d --wait']) !== 0) {
            $this->io->error('The containers did not start. Run "./vendor/bin/sage-sail up -d" to see why.');

            return self::FAILURE;
        }

        if ($this->databaseService($services) === null) {
            $this->io->note('No database service was installed, so WordPress was not installed.');

            return self::SUCCESS;
        }

        if (! $this->wordPressIsInstalled() && $this->installWordPress($input) !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->installTheme($input);
        $this->summarize($services);

        return self::SUCCESS;
    }

    private function wordPressIsInstalled(): bool
    {
        return $this->runCommands(['./vendor/bin/sage-sail wp core is-installed > /dev/null 2>&1']) === 0;
    }

    private function installWordPress(InputInterface $input): int
    {
        [$title, $user, $password, $email] = $this->gatherAdministrator($input);

        $installed = $this->runCommands([sprintf(
            './vendor/bin/sage-sail wp core install --url=%s --title=%s --admin_user=%s --admin_password=%s --admin_email=%s --skip-email',
            escapeshellarg($this->configuredSiteUrl()),
            escapeshellarg($title),
            escapeshellarg($user),
            escapeshellarg($password),
            escapeshellarg($email)
        )]);

        if ($installed !== 0) {
            $this->io->error('WordPress could not be installed. The containers are running, so you may retry with "./vendor/bin/sage-sail wp core install".');

            return self::FAILURE;
        }

        $this->administrator = [$user, $password];

        return self::SUCCESS;
    }

    /**
     * Install a Sage theme into the Bedrock themes directory, activate it, and build its assets.
     */
    private function installTheme(InputInterface $input): void
    {
        $theme = $this->resolveTheme($input);

        if ($theme === null) {
            return;
        }

        $path = 'web/app/themes/' . $theme;

        if (is_dir($this->project->path($path))) {
            $this->io->note(sprintf('The "%s" theme already exists and was left untouched.', $theme));
        } elseif ($this->runCommands([sprintf(
            './vendor/bin/sage-sail composer create-project roots/sage %s --no-interaction',
            escapeshellarg($path)
        )]) !== 0) {
            $this->io->error('The Sage theme could not be installed.');

            return;
        }

        if ($this->runCommands(['./vendor/bin/sage-sail wp theme activate ' . escapeshellarg($theme)]) !== 0) {
            $this->io->error(sprintf('The "%s" theme could not be activated.', $theme));

            return;
        }

        // A theme without built assets renders unstyled, so this is part of installing it...
        if ($this->runCommands([sprintf(
            './vendor/bin/sage-sail run bash -c %s',
            escapeshellarg(sprintf('cd %s && npm install --no-audit --no-fund && npm run build', $path))
        )]) !== 0) {
            $this->io->warning(sprintf('The theme assets could not be built. Retry inside %s with "npm install && npm run build".', $path));
        }

        $this->theme = $theme;
    }

    private function resolveTheme(InputInterface $input): ?string
    {
        $theme = $input->getOption('theme');

        if ($theme === null && $input->isInteractive()) {
            $theme = text(
                label: 'Name of the Sage theme to install',
                placeholder: 'Leave empty to skip',
                default: 'sage',
            );
        }

        if ($theme === null || trim((string) $theme) === '') {
            return null;
        }

        $theme = trim((string) $theme);

        // The name becomes both a directory and a shell argument...
        if (preg_match('/^[a-z0-9][a-z0-9-]*$/', $theme) !== 1) {
            $this->io->error(sprintf('Invalid theme name [%s]. Use lowercase letters, numbers, and dashes.', $theme));

            return null;
        }

        return $theme;
    }

    /**
     * @param  array<int, string>  $services
     */
    private function summarize(array $services): void
    {
        $url = $this->configuredSiteUrl();

        $this->io->success('Sage Sail is up.');
        $this->io->writeln(sprintf('  Site:  <options=bold>%s</>', $url));

        $this->io->writeln($this->administrator === null
            ? sprintf('  Admin: <options=bold>%s/wp/wp-admin</>', $url)
            : sprintf('  Admin: <options=bold>%s/wp/wp-admin</> (%s / %s)', $url, $this->administrator[0], $this->administrator[1]));

        if ($this->theme !== null) {
            $this->io->writeln(sprintf('  Theme: <options=bold>%s</> (web/app/themes/%s)', $this->theme, $this->theme));
        }

        if (in_array('mailpit', $services, true)) {
            $this->io->writeln(sprintf('  Mail:  <options=bold>http://localhost:%s</>', $this->mailpitPort()));
        }

        $this->io->newLine();
    }

    private function mailpitPort(): string
    {
        $envPath = $this->project->envPath();

        if ($envPath === null) {
            return '8025';
        }

        return (new Environment($envPath))->get('FORWARD_MAILPIT_DASHBOARD_PORT') ?? '8025';
    }

    private function configuredSiteUrl(): string
    {
        $envPath = $this->project->envPath();

        if ($envPath === null) {
            return 'http://localhost';
        }

        return (new Environment($envPath))->get('WP_HOME') ?? 'http://localhost';
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: string}
     */
    private function gatherAdministrator(InputInterface $input): array
    {
        $title = $input->getOption('title') ?? basename($this->project->path());
        $user = $input->getOption('admin-user');
        $password = $input->getOption('admin-password');
        $email = $input->getOption('admin-email');

        if (! $input->isInteractive()) {
            return [$title, $user, $password, $email];
        }

        return [
            text(label: 'Site title', default: $title),
            text(label: 'Administrator username', default: $user),
            text(label: 'Administrator password', default: $password),
            text(label: 'Administrator email', default: $email),
        ];
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
