<?php

namespace Core\Auth;

use Core\Application;
use Core\Contracts\Auth\Guard;
use Core\Contracts\Auth\UserProvider;
use InvalidArgumentException;

/**
 * @mixin \Core\Contracts\Auth\Guard
 */
class AuthManager
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

        if ($config['driver'] === 'session') {
            $provider = $this->createUserProvider($config['provider'] ?? null);
            return new SessionGuard($this->app, $this->app->make(\Core\Session\SessionManager::class), $provider);
        }
        if ($config['driver'] === 'jwt') {
            $provider = $this->createUserProvider($config['provider'] ?? null);
            return new JwtGuard($this->app->make(\Http\Request::class), $provider);
        }

        throw new InvalidArgumentException("Auth driver [{$config['driver']}] for guard [{$name}] is not supported.");
    }

    protected function createUserProvider(?string $providerName): UserProvider
    {
        if (is_null($providerName)) {
            throw new InvalidArgumentException("Auth provider not defined for guard.");
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

    public function reset(): void
    {
        // Reset all resolved guards
        foreach ($this->guards as $guard) {
            if (method_exists($guard, 'reset')) {
                $guard->reset();
            }
        }
    }

    public function __call(string $method, array $arguments)
    {
        return $this->guard()->$method(...$arguments);
    }
}