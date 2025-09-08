<?php

namespace App\Providers;

use Core\Redis\RedisManager;
use Core\Support\ServiceProvider;
use Redis;

class RedisServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RedisManager::class, function ($app) {
            $config = $app->make('config')->get('redis');
            return new RedisManager($app, $config);
        });

        $this->app->alias(RedisManager::class, 'redis');

        $this->app->bind(Redis::class, function ($app) {
            return $app->make('redis')->connection();
        });
    }

}
