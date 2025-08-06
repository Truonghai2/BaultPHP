<?php

namespace App\Providers;

use Core\Config;
use Core\Support\ServiceProvider;

class ConfigServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('config', function ($app) {
            $items = [];
            // We are not loading all config files here anymore.
            // Instead, config values will be loaded on demand.

            return new Config($items);
        });
    }
}
