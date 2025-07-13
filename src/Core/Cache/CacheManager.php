<?php

namespace Core\Cache;

use Core\Application;
use Predis\Client as RedisClient;

class CacheManager
{
    protected Application $app;
    protected array $stores = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function store(string $name = null)
    {
        $name = $name ?: $this->getDefaultDriver();
        return $this->stores[$name] ??= $this->resolve($name);
    }

    protected function resolve(string $name)
    {
        $config = $this->app->make('config')->get("cache.stores.{$name}");

        if (is_null($config)) {
            throw new \InvalidArgumentException("Cache store [{$name}] is not configured.");
        }

        if ($config['driver'] === 'redis') {
            return $this->createRedisDriver($config);
        }

        throw new \InvalidArgumentException("Driver [{$config['driver']}] is not supported.");
    }

    protected function createRedisDriver(array $config): RedisClient
    {
        $redisConfig = $this->app->make('config')->get("database.redis.{$config['connection']}");
        return new RedisClient([
            'scheme' => 'tcp',
            'host'   => $redisConfig['host'],
            'port'   => $redisConfig['port'],
            'password' => $redisConfig['password'],
            'database' => $redisConfig['database'],
        ]);
    }

    public function getDefaultDriver(): string
    {
        return $this->app->make('config')->get('cache.default');
    }

    public function __call(string $method, array $parameters)
    {
        // Proxy calls to the default store
        return $this->store()->{$method}(...$parameters);
    }
}