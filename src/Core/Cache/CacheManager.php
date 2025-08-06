<?php

namespace Core\Cache;

use Core\Application;
use Core\Contracts\Cache\Store;
use InvalidArgumentException;

class CacheManager
{
    protected Application $app;
    protected array $stores = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function store($name = null): Store
    {
        $name = $name ?: $this->getDefaultDriver();
        return $this->stores[$name] = $this->get($name);
    }

    protected function get($name): Store
    {
        return $this->stores[$name] ?? $this->resolve($name);
    }

    protected function resolve($name): Store
    {
        $config = $this->getConfig($name);
        $driverMethod = 'create' . ucfirst($config['driver']) . 'Driver';

        if (method_exists($this, $driverMethod)) {
            return $this->{$driverMethod}($config);
        }

        throw new InvalidArgumentException("Driver [{$config['driver']}] is not supported.");
    }

    /**
     * Tạo một instance của Redis cache driver.
     *
     * @param  array  $config
     * @return \Core\Cache\RedisStore
     */
    protected function createRedisDriver(array $config): Store
    {
        $redis = $this->app->make('redis');
        return new RedisStore($redis, $this->app->make('config')->get('cache.prefix', 'bault_cache'));
    }

    /**
     * Tạo một instance của file cache driver.
     *
     * @param  array  $config
     * @return \Core\Cache\FileStore
     */
    protected function createFileDriver(array $config): FileStore
    {
        // Giả định rằng 'files' đã được bind vào container.
        // Nếu chưa, bạn cần tạo một FilesystemServiceProvider.
        $filesystem = $this->app->make(\Illuminate\Filesystem\Filesystem::class);

        return new FileStore($filesystem, $config['path']);
    }

    /**
     * Lưu một item vào cache trong một số giây nhất định.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @param  int  $seconds
     * @return bool
     */
    public function put(string $key, mixed $value, int $seconds): bool
    {
        return $this->store()->put($key, $value, $seconds);
    }

    /**
     * Xóa một item khỏi cache.
     *
     * @param  string  $key
     * @return bool
     */
    public function forget(string $key): bool
    {
        return $this->store()->forget($key);
    }

    public function getDefaultDriver(): string
    {
        return $this->app->make('config')->get('cache.default');
    }

    protected function getConfig($name): array
    {
        return $this->app->make('config')->get("cache.stores.{$name}");
    }

    public function __call($method, $parameters)
    {
        return $this->store()->$method(...$parameters);
    }
}
