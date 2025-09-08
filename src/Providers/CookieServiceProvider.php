<?php

namespace App\Providers;

use Core\Contracts\StatefulService;
use Core\Cookie\CookieManager;
use Core\Support\ServiceProvider;

class CookieServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CookieManager::class);

        $this->app->tag(CookieManager::class, StatefulService::class);
    }
}
