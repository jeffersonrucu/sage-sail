<?php

namespace Sage\Sail;

use RuntimeException;

class Project
{
    /**
     * Possible names for the compose file according to the Compose specification.
     *
     * @var array<int, string>
     */
    private const COMPOSE_PATHS = [
        'compose.yaml',
        'compose.yml',
        'docker-compose.yaml',
        'docker-compose.yml',
    ];

    public function __construct(private readonly string $basePath)
    {
    }

    public static function fromWorkingDirectory(): self
    {
        $cwd = getcwd();

        if ($cwd === false) {
            throw new RuntimeException('Unable to determine the current working directory.');
        }

        return new self($cwd);
    }

    public function path(string $path = ''): string
    {
        return $path === ''
            ? $this->basePath
            : $this->basePath . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    }

    /**
     * Determine whether the working directory looks like a Bedrock installation.
     */
    public function isBedrock(): bool
    {
        return is_dir($this->path('web')) && is_file($this->path('config/application.php'));
    }

    /**
     * Get the path to an existing compose file, falling back to "compose.yaml".
     */
    public function composePath(): string
    {
        foreach (self::COMPOSE_PATHS as $path) {
            if (is_file($this->path($path))) {
                return $this->path($path);
            }
        }

        return $this->path('compose.yaml');
    }

    /**
     * Get the path to the project ".env" file, seeding it from ".env.example" when absent.
     */
    public function envPath(): ?string
    {
        $env = $this->path('.env');

        if (is_file($env)) {
            return $env;
        }

        $example = $this->path('.env.example');

        if (! is_file($example)) {
            return null;
        }

        copy($example, $env);

        return $env;
    }
}
