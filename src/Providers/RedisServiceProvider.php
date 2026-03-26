<?php

namespace App\Providers;

use Core\Redis\RedisManager;
use Core\Support\ServiceProvider;

class RedisServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('redis.legacy', function ($app) {
            $config = $app->make('config')->get('redis', []);
            $config['default'] = $config['default'] ?? 'default';
            return new RedisManager($app, $config);
        });

        $this->app->alias('redis.legacy', RedisManager::class);
    }
}
