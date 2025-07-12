<?php

namespace App\Providers;

use Core\Auth\AuthManager;
use Core\Auth\RequestGuard;
use Core\Auth\SessionGuard;
use Core\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AuthManager::class, function ($app) {
            return new AuthManager($app);
        });

        // Guards are resolved via the AuthManager, so we don't need to register them here directly.
        // The AuthManager will create and cache them as singletons for the request lifecycle.
    }
}