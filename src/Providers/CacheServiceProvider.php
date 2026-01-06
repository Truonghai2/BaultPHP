<?php

namespace App\Providers;

use Core\Cache\CacheManager;
use Core\Cache\CacheManager as CoreCacheManager;
use Core\Cache\TraceableCacheManager;
use Core\Debug\DebugBroadcaster;
use Core\Debug\RealtimeTraceableCacheManager;
use Core\Support\ServiceProvider;
use Psr\SimpleCache\CacheInterface;

class CacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('cache.factory', function ($app) {
            return new CoreCacheManager($app);
        });

        $this->app->singleton('cache', function ($app) {
            $manager = $app->make('cache.factory');

            if ((bool) config('debug.enabled', false) && $app->bound('debugbar') && $app->bound(DebugBroadcaster::class)) {
                return new RealtimeTraceableCacheManager(
                    $manager,
                    $app->make('debugbar'),
                    $app->make(DebugBroadcaster::class),
                );
            }

            if ((bool) config('app.debug', false) && $app->bound('debugbar')) {
                return new TraceableCacheManager($manager, $app->make('debugbar'));
            }

            return $manager;
        });

        $this->app->alias('cache', CacheManager::class);

        $this->app->singleton(\Core\Contracts\Cache\Factory::class, \App\Cache\AppCacheManager::class);

        $this->app->singleton(CacheInterface::class, function ($app) {
            return $app->make('cache')->store();
        });
    }
}
