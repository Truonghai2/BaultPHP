<?php

namespace App\Providers;

use Core\Support\ServiceProvider;
use MeiliSearch\Client;

class MeiliSearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Client::class, function ($app) {
            $config = $app->make('config')->get('meilisearch');

            return new Client($config['host'], $config['key']);
        });
    }
}
