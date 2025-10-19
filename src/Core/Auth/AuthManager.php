<?php

namespace Core\Auth;

use Core\Cache\CacheManager;
use Core\Contracts\Auth\Guard;
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
     * The user provider manager instance.
     *
     * @var \Core\Auth\UserProviderManager
     */
    protected UserProviderManager $providerManager;

    /**
     * Get the default authentication driver name.
     *
     * @return string
     */
    public function getDefaultDriver(): string
    {
        return $this->app->make('config')->get('auth.defaults.guard');
    }

    public function __construct(\Core\Application $app)
    {
        parent::__construct($app);
        $this->providerManager = $this->app->make(UserProviderManager::class);
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
        $provider = $this->providerManager->driver($config['provider'] ?? null);

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
     * Create a new driver instance for the 'apikey' guard.
     *
     * @param string $name
     * @param array $config
     * @return ApiKeyGuard
     */
    protected function createApiKeyDriver(string $name, array $config): ApiKeyGuard
    {
        return new ApiKeyGuard(
            $name,
            $this->app,
            $this->providerManager->driver($config['provider'] ?? null),
            $config['input_key'] ?? 'api_key',
            $config['storage_key'] ?? 'key',
        );
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
     * Reset the state of the manager by clearing all resolved guard instances.
     * This is crucial in long-running applications to prevent user state
     * from leaking between requests.
     */
    public function resetState(): void
    {
        $this->drivers = [];
    }
}
