<?php

namespace Sage\Sail;

use Illuminate\Container\Container;

class ArtisanContainer extends Container
{
    /**
     * The base path for the application.
     *
     * @var string
     */
    protected $basePath;

    /**
     * Create a new container instance.
     */
    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;

        static::setInstance($this);
    }

    /**
     * Get the base path of the application.
     *
     * @param  string  $path
     * @return string
     */
    public function basePath($path = '')
    {
        return $this->basePath . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }
}
