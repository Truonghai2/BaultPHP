<?php

namespace App\Providers;

use Core\Database\Swoole\SwooleRedisPool;
use Core\Session\RedisSessionHandler;
use Core\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;

class SessionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('session', function ($app) {
            return new Session(new NativeSessionStorage());
        });

        $this->app->alias('session', Session::class);
    }

    public function boot(): void
    {
        // Set Redis handler only if the driver is 'redis' AND running in a Swoole environment.
        // This ensures that CLI commands are not affected.
        $isSwooleEnv = extension_loaded('swoole') && \Swoole\Coroutine::getCid() > 0;

        if (config('session.driver') === 'redis' && $isSwooleEnv && SwooleRedisPool::isInitialized()) {
            $handler = new RedisSessionHandler(config('session'));

            // Set custom handler for PHP
            session_set_save_handler($handler, true);

            // Configure session parameters
            ini_set('session.gc_probability', '0'); // Disable default PHP GC
        }
        // Otherwise, the framework will use the default PHP session handler (usually 'files'),
        // or another handler configured by another service provider.

        // Always start the session if it has not been started
        if (session_status() === PHP_SESSION_NONE) {
            // We need to get the session from the container to start it.
            $this->app->make('session')->start();
        }
    }
}
