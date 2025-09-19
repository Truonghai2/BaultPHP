<?php

namespace App\Providers;

use Core\Application;
use Core\Contracts\StatefulService;
use Core\Session\DirectSessionTokenStorage;
use Core\Session\SessionManager;
use Core\Session\SwoolePdoSessionHandler;
use Core\Session\SwooleRedisSessionHandler;
use Core\Support\ServiceProvider;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;
use Symfony\Component\Security\Csrf\TokenGenerator\UriSafeTokenGenerator;
use Symfony\Component\Security\Csrf\TokenStorage\TokenStorageInterface;

class SessionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SessionManager::class, function (Application $app) {
            return new SessionManager(
                $app,
                function () use ($app) { // This closure is now correctly passed to the SessionManager
                    return $this->createSessionHandler($app);
                },
            );
        });

        $this->app->tag(SessionManager::class, StatefulService::class);

        $this->app->singleton(RequestStack::class);

        $this->app->singleton('session', function ($app) {
            return $app->make(SessionManager::class)->getSession();
        });

        $this->app->alias('session', Session::class);

        $this->app->alias('session', \Symfony\Component\HttpFoundation\Session\SessionInterface::class);

        $this->registerCsrfServices();

        $this->app->singleton(\Core\Security\CsrfManager::class, function ($app) {
            return new \Core\Security\CsrfManager(
                $app->make(CsrfTokenManagerInterface::class),
            );
        });
    }

    /**
     * Create the session handler based on the configuration.
     *
     * @param \Core\Application $app
     * @return \SessionHandlerInterface
     */
    protected function createSessionHandler(Application $app): \SessionHandlerInterface
    {
        $config = $app->make('config');
        $driver = $config->get('session.driver');

        return match ($driver) {
            'database' => $this->createDatabaseHandler($app, $config),
            'redis' => $this->createRedisHandler($app, $config),
            'file' => new \Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler(),
            default => throw new InvalidArgumentException("Unsupported session driver [{$driver}]."),
        };
    }

    /**
     * Create a new database session handler instance.
     *
     * @param \Core\Application $app
     * @param \Core\Config $config
     * @return \Core\Session\SwoolePdoSessionHandler
     */
    protected function createDatabaseHandler(Application $app, \Core\Config $config): SwoolePdoSessionHandler
    {
        if (!$config->get('server.swoole.pools.database.enabled', false)) {
            throw new \RuntimeException('Database session driver requires the DB connection pool to be enabled in config/server.php.');
        }

        $connectionName = $config->get('session.database_connection') ?? $config->get('database.default');
        $table = $config->get('session.table', 'sessions');
        $lifetime = (int) $config->get('session.lifetime', 120) * 60; // Convert minutes to seconds

        return new SwoolePdoSessionHandler(
            $connectionName,
            $table,
            $lifetime,
        );
    }

    /**
     * Create a new Redis session handler instance.
     *
     * @param \Core\Application $app
     * @param \Core\Config $config
     * @return \SessionHandlerInterface
     */
    protected function createRedisHandler(Application $app, \Core\Config $config): \SessionHandlerInterface
    {
        if (!$config->get('server.swoole.pools.redis.enabled', false)) {
            throw new \RuntimeException('Redis session driver requires the Redis connection pool to be enabled in config/server.php.');
        }

        $connectionName = $config->get('session.connection', 'default');
        $lifetime = (int) $config->get('session.lifetime', 120) * 60; // Convert minutes to seconds

        return new SwooleRedisSessionHandler(
            $connectionName,
            $lifetime,
        );
    }

    /**
     * Register the bindings for the Symfony CSRF component.
     */
    protected function registerCsrfServices(): void
    {
        $this->app->singleton(TokenGeneratorInterface::class, UriSafeTokenGenerator::class);

        $this->app->singleton(TokenStorageInterface::class, function ($app) {
            return new DirectSessionTokenStorage($app->make('session'));
        });

        $this->app->singleton(CsrfTokenManagerInterface::class, CsrfTokenManager::class);
    }
}
