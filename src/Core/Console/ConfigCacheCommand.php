<?php

namespace Core\Console;

use Core\Console\Contracts\CommandInterface;
use Core\Application;
use Core\AppKernel;

class ConfigCacheCommand implements CommandInterface
{
    public function __construct(protected Application $app)
    {
    }

    public function signature(): string
    {
        return 'config:cache';
    }

    public function handle(array $arguments): void
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
    }
}