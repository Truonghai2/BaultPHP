<?php

namespace App\Providers;

use Core\Contracts\Support\DeferrableProvider;
use Core\Support\ServiceProvider;
use MeiliSearch\Client;

class MeiliSearchServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->app->singleton(Client::class, function ($app) {
            $config = $app->make('config')->get('meilisearch');

            return new Client($config['host'], $config['key']);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [Client::class];
    }
}
