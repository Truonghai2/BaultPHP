<?php

namespace App\Providers;

use Core\Database\Swoole\SwooleRedisPool;
use Core\Session\DirectSessionTokenStorage;
use Core\Session\RedisSessionHandler;
use Core\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;
use Symfony\Component\Security\Csrf\TokenGenerator\UriSafeTokenGenerator;
use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage;
use Symfony\Component\Security\Csrf\TokenStorage\TokenStorageInterface;

class SessionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // It's a good practice to register RequestStack as a singleton
        // so the same instance is used throughout the application. It's required
        // by the SessionTokenStorage.
        $this->app->singleton(RequestStack::class);

        $this->app->singleton('session', function ($app) {
            return new Session(new NativeSessionStorage());
        });

        $this->app->alias('session', Session::class);

        // Also alias the interface to the 'session' binding. This allows other services
        // to depend on the interface, making them more decoupled from the concrete implementation.
        $this->app->alias('session', \Symfony\Component\HttpFoundation\Session\SessionInterface::class);

        // Register the core CSRF services from the Symfony component.
        // This is the foundation for our CSRF protection.
        $this->registerCsrfServices();

        // Register our application's CsrfManager wrapper. This now has its
        // dependencies correctly registered in the container.
        $this->app->singleton(\Core\Security\CsrfManager::class, function ($app) {
            return new \Core\Security\CsrfManager(
                $app->make(CsrfTokenManagerInterface::class),
            );
        });
    }

    /**
     * Register the bindings for the Symfony CSRF component.
     */
    protected function registerCsrfServices(): void
    {
        $this->app->singleton(TokenGeneratorInterface::class, UriSafeTokenGenerator::class);

        $this->app->singleton(TokenStorageInterface::class, function ($app) {
            // The default SessionTokenStorage relies on a session being present on the
            // RequestStack. In this application, some parts of the code (like view rendering
            // from the router) might need a CSRF token before the StartSession middleware
            // has run, which causes a SessionNotFoundException.
            //
            // To solve this without a major architectural change, we use a custom
            // token storage that interacts with the 'session' service directly. This
            // custom storage bypasses the RequestStack and starts the session on-demand
            // if it's not already started.
            return new DirectSessionTokenStorage($app->make('session'));
        });

        $this->app->singleton(CsrfTokenManagerInterface::class, CsrfTokenManager::class);
    }

    public function boot(): void
    {
        // Set Redis handler only if the driver is 'redis' AND running in a Swoole environment.
        // This ensures that CLI commands are not affected, as session management for HTTP
        // requests is handled by the StartSession middleware.
        if (php_sapi_name() === 'cli') {
            return;
        }

        $isSwooleEnv = extension_loaded('swoole') && \Swoole\Coroutine::getCid() > 0;

        if (config('session.driver') === 'redis' && $isSwooleEnv && SwooleRedisPool::isInitialized()) {
            $handler = new RedisSessionHandler(config('session'));

            // Set custom handler for PHP. The session itself will be started by the StartSession middleware.
            session_set_save_handler($handler, true);

            // Configure session parameters
            ini_set('session.gc_probability', '0'); // Disable default PHP GC
        }
        // For other drivers or environments, we rely on the default PHP session handling,
        // which will be initiated by the StartSession middleware.
    }
}
