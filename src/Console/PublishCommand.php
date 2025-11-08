<?php

namespace Laravel\Sail\Console;

use Illuminate\Console\Command;
use Laravel\Sail\Console\Concerns\InteractsWithDockerComposeServices;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'sage-sail:publish')]
class PublishCommand extends Command
{
    use InteractsWithDockerComposeServices;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sage-sail:publish';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish the Laravel Sage Sail Docker files';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->call('vendor:publish', ['--tag' => 'sail-docker']);
        $this->call('vendor:publish', ['--tag' => 'sail-database']);

        file_put_contents(
            $this->composePath(),
            str_replace(
                [
                    './vendor/jeffersonrucu/sage-sail/runtimes/8.4',
                    './vendor/jeffersonrucu/sage-sail/runtimes/8.3',
                    './vendor/jeffersonrucu/sage-sail/runtimes/8.2',
                    './vendor/jeffersonrucu/sage-sail/runtimes/8.1',
                    './vendor/jeffersonrucu/sage-sail/runtimes/8.0',
                    './vendor/jeffersonrucu/sage-sail/database/mysql',
                    './vendor/jeffersonrucu/sage-sail/database/pgsql'
                ],
                [
                    './docker/8.4',
                    './docker/8.3',
                    './docker/8.2',
                    './docker/8.1',
                    './docker/8.0',
                    './docker/mysql',
                    './docker/pgsql'
                ],
                file_get_contents($this->composePath())
            )
        );
    }
}
