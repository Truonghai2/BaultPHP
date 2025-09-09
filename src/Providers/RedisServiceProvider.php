<?php

namespace App\Providers;

use Core\Redis\FiberRedisManager;
use Core\Support\ServiceProvider;

class RedisServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->alias(FiberRedisManager::class, 'redis');
    }
}
