<?php

namespace Core\Auth;

use Core\Contracts\Auth\UserProvider;
use Core\Manager;
use InvalidArgumentException;

/**
 * Manages the creation of UserProvider instances.
 * This class adheres to the Single Responsibility Principle by separating
 * provider creation logic from guard creation logic.
 */
class UserProviderManager extends Manager
{
    /**
     * Create a new driver instance.
     *
     * @param string $name The name of the provider configuration.
     * @return UserProvider
     */
    protected function createDriver($name): UserProvider
    {
        $config = $this->getProviderConfig($name);

        if (!isset($config['driver'])) {
            throw new InvalidArgumentException("Auth provider driver not configured for [{$name}].");
        }

        $method = 'create' . ucfirst($config['driver']) . 'Driver';

        if (method_exists($this, $method)) {
            return $this->$method($config);
        }

        throw new InvalidArgumentException("Auth provider driver [{$config['driver']}] is not supported.");
    }

    /**
     * Create an instance of the Eloquent user provider.
     *
     * @param array $config
     * @return EloquentUserProvider
     */
    protected function createOrmDriver(array $config): EloquentUserProvider
    {
        return new EloquentUserProvider($config['model'], $this->app);
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

    public function getDefaultDriver(): string
    {
        return 'orm';
    }
}
