<?php

declare(strict_types=1);

namespace App\Providers;

use Core\Hashing\HashManager;
use Core\Support\ServiceProvider;

class HashServiceProvider extends ServiceProvider
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
}
