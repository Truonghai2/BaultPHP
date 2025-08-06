<?php

namespace App\Providers;

use Core\Session\SessionManager;
use Core\Support\ServiceProvider;

class SessionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SessionManager::class, function () {
            return new SessionManager();
        });
    }
}
