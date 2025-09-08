<?php

namespace Core\Support\Facades;

use Core\Support\Facade;

/**
 * @method static \Core\Contracts\Cache\Store store(string|null $name = null)
 * @method static bool has(string $key)
 * @method static mixed get(string $key, mixed $default = null)
 * @method static bool put(string $key, mixed $value, \DateTimeInterface|\DateInterval|int|null $ttl = null)
 * @method static bool forget(string $key)
 * @method static bool clear()
 *
 * @see \Core\Cache\CacheManager
 */
class Cache extends Facade
{
    /**
     * Get the registered name of the component in the container.
     *
     * This MUST match the key used in the CacheServiceProvider.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        // 'cache' là key bạn đã đăng ký trong CacheServiceProvider.
        return 'cache';
    }
}
