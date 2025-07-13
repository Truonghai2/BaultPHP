<?php

namespace App\Providers;

use Core\Cache\CacheManager;
use Core\Support\ServiceProvider;

class CacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('cache', function ($app) {
            return new CacheManager($app);
        });
    }
}