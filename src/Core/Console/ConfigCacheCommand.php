<?php

namespace Core\Console;

use Core\Application;
use Core\AppKernel;

class ConfigCacheCommand
{
    protected string $name = 'config:cache';
    protected string $description = 'Create a service provider cache file for faster application bootstrapping.';

    public function __construct(protected Application $app)
    {
    }

    public function handle(): int
    {
        echo "Caching configuration...\n";

        $cachePath = $this->app->getCachedProvidersPath();
        if (file_exists($cachePath)) {
            unlink($cachePath);
        }

        // Get the AppKernel from the container to use it as the single source of truth.
        $kernel = $this->app->make(AppKernel::class);

        $providers = $kernel->getProvidersForCaching();

        $content = '<?php' . PHP_EOL . PHP_EOL . 'return ' . var_export($providers, true) . ';';
        file_put_contents($cachePath, $content);

        echo "Configuration cached successfully!\n";

        return 0;
    }
}