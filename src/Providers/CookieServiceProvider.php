<?php

namespace App\Providers;

use Core\Contracts\StatefulService;
use Core\Cookie\CookieManager;
use Core\Support\ServiceProvider;

class CookieServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $cookieManager = new CookieManager(
            $this->app->make('log'),
            $this->app->make('encrypter'),
        );

        $this->app->instance(CookieManager::class, $cookieManager);
        $this->app->alias(CookieManager::class, 'cookies');

        $this->app->tag(CookieManager::class, StatefulService::class);
    }
}
