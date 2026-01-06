<?php

declare(strict_types=1);

namespace App\Providers;

use Core\Contracts\Support\DeferrableProvider;
use Core\Hashing\AdaptiveHashManager;
use Core\Hashing\HashManager;
use Core\Support\ServiceProvider;

class HashServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->singleton('hash', function ($app) {
            // Use AdaptiveHashManager if enabled in config
            $useAdaptive = $app['config']['hashing.adaptive'] ?? false;

            return $useAdaptive
                ? new AdaptiveHashManager($app)
                : new HashManager($app);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * This provider will only be loaded when one of these services is requested from the container.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return ['hash', HashManager::class, AdaptiveHashManager::class];
    }
}
