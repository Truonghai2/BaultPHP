<?php

namespace Core\Support;

use Core\Application;

abstract class ServiceProvider
{
    public function __construct(protected Application $app)
    {
    }

    public function register(): void
    {
    }

    public function boot(): void
    {
    }

    protected function commands(array $commands): void
    {
        foreach ($commands as $command) {
            $this->app->bind($command, fn () => new $command());
        }
    }

    /**
     * Merge the given configuration with the existing configuration.
     */
    protected function mergeConfigFrom(string $path, string $key): void
    {
        /** @var \Core\Config $config */
        $config = $this->app->make('config');

        $newConfig = require $path;
        $existingConfig = $config->get($key, []);
        $config->set($key, array_merge_recursive($existingConfig, $newConfig));
    }

    /**
     * Register a path for migrations.
     *
     * @param string $path
     * @return void
     */
    protected function loadMigrationsFrom(string $path): void
    {
        $config = $this->app->make('config');
        $paths = $config->get('database.migrations.paths', []);
        $config->set('database.migrations.paths', array_unique(array_merge($paths, [$path])));
    }

    protected function loadViewsFrom(string $path, string $namespace): void
    {
        $this->app->make('view')->addNamespace($namespace, $path);
    }
}
