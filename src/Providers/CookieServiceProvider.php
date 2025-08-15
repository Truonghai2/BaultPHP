<?php

namespace App\Providers;

use Core\Contracts\StatefulService;
use Core\Cookie\CookieManager;
use Core\Encryption\Encrypter;
use Core\Support\ServiceProvider;

class CookieServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Encrypter::class, function ($app) {
            $config = $app->make('config');
            $key = $config->get('app.key');

            if (str_starts_with($key, 'base64:')) {
                $key = base64_decode(substr($key, 7));
            }

            return new Encrypter($key);
        });

        $this->app->singleton(CookieManager::class, function ($app) {
            return new CookieManager($app->make(Encrypter::class));
        });

        // Tag the CookieManager as a stateful service to be reset after each request.
        $this->app->tag(CookieManager::class, StatefulService::class);
    }
}
