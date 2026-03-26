<?php

namespace App\Providers;

use Core\Contracts\StatefulService;
use Core\Cookie\CookieManager;
use Core\Support\ServiceProvider;

class CookieServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CookieManager::class, function ($app) {
            return new CookieManager(
                $app->make('log'),
                $app->make('encrypter'),
            );
        });

        $this->app->alias(CookieManager::class, 'cookies');

        $this->app->tag(CookieManager::class, StatefulService::class);
    }
}
