<?php

namespace Core\Auth;

use Core\Cache\CacheManager;
use Core\Contracts\Auth\Guard;
use Core\Contracts\Auth\UserProvider;
use Core\Contracts\StatefulService;
use Core\Manager;
use InvalidArgumentException;
use League\OAuth2\Server\ResourceServer;

/**
 * @mixin \Core\Contracts\Auth\Guard
 */
class AuthManager extends Manager implements StatefulService
{
    /**
     * Get the default authentication driver name.
     *
     * @return string
     */
    public function getDefaultDriver(): string
    {
        return $this->app->make('config')->get('auth.defaults.guard');
    }

    /**
     * Get a guard instance.
     *
     * @param string|null $name
     * @return Guard
     */
    public function guard(string $name = null): Guard
    {
        return $this->driver($name);
    }

    /**
     * Create a new driver instance.
     *
     * @param string $name
     * @return mixed
     */
    protected function createDriver($name)
    {
        $method = 'create' . ucfirst($this->getDriverConfig($name)) . 'Driver';

        if (method_exists($this, $method)) {
            return $this->$method($name, $this->getGuardConfig($name));
        }

        throw new InvalidArgumentException("Driver [{$this->getDriverConfig($name)}] for guard [{$name}] is not supported.");
    }

    /**
     * Create a new driver instance for the 'session' guard.
     *
     * @param array $config
     * @return SessionGuard
     */
    protected function createSessionDriver(string $name, array $config): SessionGuard
    {
        $provider = $this->createUserProvider($config['provider'] ?? null);

        return new SessionGuard(
            $name,
            $this->app,
            $this->app->make('session'),
            $provider,
        );
    }

    /**
     * Create a new driver instance for the 'token' guard.
     *
     * @param array $config
     * @return TokenGuard
     */
    protected function createTokenDriver(string $name, array $config): TokenGuard
    {
        return new TokenGuard(
            $name,
            $this->app,
            $this->app->make(ResourceServer::class),
            $this->app->make(CacheManager::class),
        );
    }

    /**
     * Create the user provider implementation for a guard.
     *
     * @param string|null $providerName
     * @return UserProvider
     * @throws \InvalidArgumentException
     */
    public function createUserProvider(?string $providerName): UserProvider
    {
        if (is_null($providerName)) {
            throw new InvalidArgumentException('Auth provider not defined for guard.');
        }

        $config = $this->getProviderConfig($providerName);

        if (!isset($config['driver'])) {
            throw new InvalidArgumentException("Auth provider driver not configured for [{$providerName}].");
        }

        switch ($config['driver']) {
            case 'orm':
                return new EloquentUserProvider($config['model']);
            default:
                throw new InvalidArgumentException("Auth provider driver [{$config['driver']}] is not supported.");
        }
    }

    /**
     * Get the configuration for a specific guard.
     *
     * @param string $name
     * @return array
     */
    protected function getGuardConfig(string $name): array
    {
        return $this->app->make('config')->get("auth.guards.{$name}", []);
    }

    /**
     * Get the driver name for a specific guard.
     *
     * @param string $name
     * @return string
     */
    protected function getDriverConfig(string $name): string
    {
        return $this->app->make('config')->get("auth.guards.{$name}.driver");
    }

    /**
     * Get the configuration for a specific provider.
     *
     * @param string $name
     * @return array
     */
    protected function getProviderConfig(string $name): array
    {
        return $this->app->make('config')->get("auth.providers.{$name}", []);
    }

    /**
     * Reset the state of the manager by clearing all resolved guard instances.
     * This is crucial in long-running applications to prevent user state
     * from leaking between requests.
     */
    public function resetState(): void
    {
        $this->drivers = [];
    }
}
