<?php

namespace Core\Auth;

use Core\Application;
use Core\Cache\CacheManager;
use Core\Contracts\Auth\Guard;
use Core\Contracts\Auth\UserProvider;
use Core\Contracts\StatefulService;
use InvalidArgumentException;
use League\OAuth2\Server\ResourceServer;

/**
 * @mixin \Core\Contracts\Auth\Guard
 */
class AuthManager implements StatefulService
{
    protected Application $app;
    protected array $guards = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function guard(string $name = null): Guard
    {
        $name = $name ?: $this->getDefaultDriver();

        return $this->guards[$name] ??= $this->resolve($name);
    }

    protected function resolve(string $name): Guard
    {
        $config = $this->getGuardConfig($name);

        if (!isset($config['driver'])) {
            throw new InvalidArgumentException("Auth guard [{$name}] is not defined.");
        }

        $driver = $config['driver'];

        switch ($driver) {
            case 'session':
                $provider = $this->createUserProvider($config['provider'] ?? null);
                return new SessionGuard(
                    $this->app,
                    $this->app->make('session'),
                    $provider,
                );

            case 'token':
                return new TokenGuard(
                    $this->app,
                    $this->app->make(ResourceServer::class),
                    $this->app->make(CacheManager::class),
                );

            default:
                throw new InvalidArgumentException("Auth driver [{$driver}] for guard [{$name}] is not supported.");
        }
    }

    protected function createUserProvider(?string $providerName): UserProvider
    {
        if (is_null($providerName)) {
            throw new InvalidArgumentException('Auth provider not defined for guard.');
        }

        $config = $this->app->make('config')->get("auth.providers.{$providerName}");

        if ($config['driver'] === 'orm') {
            return new EloquentUserProvider($config['model']);
        }

        throw new InvalidArgumentException("Auth provider driver [{$config['driver']}] is not supported.");
    }

    protected function getGuardConfig(string $name): array
    {
        return $this->app->make('config')->get("auth.guards.{$name}");
    }

    public function getDefaultDriver(): string
    {
        return $this->app->make('config')->get('auth.defaults.guard', 'web');
    }

    /**
     * Reset the state of the manager by clearing all resolved guard instances.
     * This is crucial in long-running applications like Swoole to prevent
     * user state from leaking between requests.
     */
    public function resetState(): void
    {
        $this->guards = [];
    }

    public function __call(string $method, array $arguments)
    {
        return $this->guard()->$method(...$arguments);
    }
}
