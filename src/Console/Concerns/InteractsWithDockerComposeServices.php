<?php

namespace Sage\Sail\Console\Concerns;

use Sage\Sail\Environment;
use Symfony\Component\Yaml\Yaml;

use function Laravel\Prompts\multiselect;

trait InteractsWithDockerComposeServices
{
    /**
     * The application service key within the compose file.
     */
    protected string $appService = 'sage.test';

    /**
     * The services that may be installed.
     *
     * WordPress only supports MySQL and MariaDB, so no other database is offered.
     *
     * @var array<int, string>
     */
    protected array $services = [
        'mysql',
        'mariadb',
        'redis',
        'valkey',
        'memcached',
        'meilisearch',
        'typesense',
        'minio',
        'rustfs',
        'mailpit',
        'rabbitmq',
        'selenium',
        'soketi',
    ];

    /**
     * The services used when running without interaction.
     *
     * @var array<int, string>
     */
    protected array $defaultServices = ['mysql', 'redis', 'mailpit'];

    /**
     * The services that need a named volume to persist their data.
     *
     * @var array<int, string>
     */
    protected array $persistentServices = [
        'mysql',
        'mariadb',
        'redis',
        'valkey',
        'meilisearch',
        'typesense',
        'minio',
        'rustfs',
        'rabbitmq',
    ];

    /**
     * The environment variables each service exposes to the project.
     *
     * @var array<string, array<string, string>>
     */
    protected array $serviceEnvironment = [
        'redis' => [
            'WP_REDIS_HOST' => 'redis',
            'WP_REDIS_PORT' => '6379',
        ],
        'valkey' => [
            'WP_REDIS_HOST' => 'valkey',
            'WP_REDIS_PORT' => '6379',
        ],
        'memcached' => [
            'MEMCACHED_HOST' => 'memcached',
            'MEMCACHED_PORT' => '11211',
        ],
        'mailpit' => [
            'SMTP_HOST' => 'mailpit',
            'SMTP_PORT' => '1025',
        ],
        'meilisearch' => [
            'MEILISEARCH_HOST' => 'http://meilisearch:7700',
        ],
        'typesense' => [
            'TYPESENSE_HOST' => 'typesense',
            'TYPESENSE_PORT' => '8108',
            'TYPESENSE_PROTOCOL' => 'http',
            'TYPESENSE_API_KEY' => 'xyz',
        ],
        'minio' => [
            'S3_ENDPOINT' => 'http://minio:9000',
            'S3_ACCESS_KEY_ID' => 'sage',
            'S3_SECRET_ACCESS_KEY' => 'password',
            'S3_USE_PATH_STYLE_ENDPOINT' => 'true',
        ],
        'rustfs' => [
            'S3_ENDPOINT' => 'http://rustfs:9000',
            'S3_ACCESS_KEY_ID' => 'sage',
            'S3_SECRET_ACCESS_KEY' => 'password',
            'S3_USE_PATH_STYLE_ENDPOINT' => 'true',
        ],
        'rabbitmq' => [
            'RABBITMQ_HOST' => 'rabbitmq',
            'RABBITMQ_PORT' => '5672',
        ],
        'soketi' => [
            'PUSHER_HOST' => 'soketi',
            'PUSHER_PORT' => '6001',
            'PUSHER_SCHEME' => 'http',
            'PUSHER_APP_ID' => 'app-id',
            'PUSHER_APP_KEY' => 'app-key',
            'PUSHER_APP_SECRET' => 'app-secret',
        ],
    ];

    /**
     * @return array<int, string>
     */
    protected function gatherServicesInteractively(): array
    {
        $selected = multiselect(
            label: 'Which services would you like to install?',
            options: $this->services,
            default: ['mysql'],
        );

        return array_values(array_intersect($this->services, $selected));
    }

    /**
     * @param  array<int, string>  $services
     */
    protected function buildDockerCompose(array $services, string $phpVersion): void
    {
        $composePath = $this->project->composePath();

        $compose = is_file($composePath)
            ? Yaml::parseFile($composePath)
            : Yaml::parse((string) file_get_contents($this->stubPath('compose')));

        if (! array_key_exists($this->appService, $compose['services'] ?? [])) {
            $this->io->warning(sprintf(
                'Could not find the "%s" service. Add [%s] to its depends_on configuration manually.',
                $this->appService,
                implode(', ', $services)
            ));
        } else {
            // The MariaDB image ships a different client package than MySQL...
            if (in_array('mariadb', $services, true)) {
                $compose['services'][$this->appService]['build']['args']['MYSQL_CLIENT'] = 'mariadb-client';
            }

            $compose['services'][$this->appService]['depends_on'] = array_values(array_unique(array_merge(
                $compose['services'][$this->appService]['depends_on'] ?? [],
                $services
            )));
        }

        foreach ($services as $service) {
            if (array_key_exists($service, $compose['services'] ?? [])) {
                continue;
            }

            $compose['services'][$service] = Yaml::parseFile($this->stubPath($service))[$service];
        }

        foreach ($services as $service) {
            $volume = 'sage-sail-' . $service;

            if (! in_array($service, $this->persistentServices, true) || array_key_exists($volume, $compose['volumes'] ?? [])) {
                continue;
            }

            $compose['volumes'][$volume] = ['driver' => 'local'];
        }

        if (empty($compose['volumes'])) {
            unset($compose['volumes']);
        }

        $yaml = Yaml::dump($compose, 20, 4, Yaml::DUMP_OBJECT_AS_MAP);

        file_put_contents($composePath, str_replace('{{PHP_VERSION}}', $phpVersion, $yaml));
    }

    /**
     * Point the project's ".env" file at the installed services.
     *
     * @param  array<int, string>  $services
     */
    protected function configureEnvironment(array $services): void
    {
        $envPath = $this->project->envPath();

        if ($envPath === null) {
            $this->io->warning('No ".env" or ".env.example" file was found. Skipping environment configuration.');

            return;
        }

        $env = new Environment($envPath);

        if ($database = $this->databaseService($services)) {
            $env->set('DB_HOST', $database);
            $env->set('DB_NAME', 'sage');
            $env->set('DB_USER', 'sage');
            $env->set('DB_PASSWORD', 'password');
        }

        foreach ($services as $service) {
            foreach ($this->serviceEnvironment[$service] ?? [] as $key => $value) {
                $env->set($key, $value);
            }
        }

        $env->save();
    }

    /**
     * @param  array<int, string>  $services
     */
    protected function databaseService(array $services): ?string
    {
        foreach (['mysql', 'mariadb'] as $database) {
            if (in_array($database, $services, true)) {
                return $database;
            }
        }

        return null;
    }

    protected function installDevContainer(): void
    {
        if (! is_dir($this->project->path('.devcontainer'))) {
            mkdir($this->project->path('.devcontainer'), 0755, true);
        }

        copy($this->stubPath('devcontainer'), $this->project->path('.devcontainer/devcontainer.json'));

        if ($envPath = $this->project->envPath()) {
            $env = new Environment($envPath);
            $env->set('WWWGROUP', '1000');
            $env->set('WWWUSER', '1000');
            $env->save();
        }
    }

    /**
     * Pull and build the images required by the installation.
     *
     * @param  array<int, string>  $services
     */
    protected function prepareInstallation(array $services): void
    {
        // Ensure docker is installed...
        if ($this->runCommands(['docker info > /dev/null 2>&1']) !== 0) {
            $this->io->warning('Docker is not running. Skipping image pull and build.');

            return;
        }

        if ($services !== []) {
            $this->runCommands(['./vendor/bin/sage-sail pull ' . implode(' ', $services)]);
        }

        $this->runCommands(['./vendor/bin/sage-sail build']);
    }

    protected function stubPath(string $name): string
    {
        return dirname(__DIR__, 3) . '/stubs/' . $name . '.stub';
    }
}
