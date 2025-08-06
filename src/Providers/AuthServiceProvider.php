<?php

namespace App\Providers;

use Core\Auth\AuthManager;
use Core\Contracts\StatefulService;
use Core\Support\ServiceProvider;
use Modules\User\Domain\Services\AccessControlService;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AuthManager::class, function ($app) {
            return new AuthManager($app);
        });

        // Gán tag cho AuthManager để nó được reset sau mỗi request.
        $this->app->tag(AuthManager::class, \Core\Contracts\StatefulService::class);

        // Guards are resolved via the AuthManager, so we don't need to register them here directly.
        // The AuthManager will create and cache them as singletons for the request lifecycle.
    }

    public function boot(): void
    {
        // The 'before' callback for superuser checks has been moved directly into
        // the AccessControlService's caching logic for better performance.
        // The `before` method can still be used for other dynamic, high-level checks if needed.
    }
}
