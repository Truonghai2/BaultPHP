<?php

declare(strict_types=1);

namespace App\Providers;

use Core\Contracts\Support\DeferrableProvider;
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
            return new HashManager($app);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return ['hash', HashManager::class];
    }
}
