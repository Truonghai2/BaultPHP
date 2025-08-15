<?php

namespace App\Providers;

use Core\Auth\AuthManager;
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

        // Đăng ký AccessControlService như một singleton.
        // Điều này cho phép chúng ta inject nó và sử dụng Facade.
        $this->app->singleton(\Modules\User\Domain\Services\AccessControlService::class);
        // Tạo alias 'gate' để Facade có thể sử dụng.
        $this->app->alias(\Modules\User\Domain\Services\AccessControlService::class, 'gate');

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
