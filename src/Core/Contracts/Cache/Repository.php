<?php

namespace Core\Contracts\Cache;

/**
 * Interface Repository
 *
 * Defines the standard contract for interacting with a cache store.
 * Any cache driver (Redis, file, array, etc.) in the application should
 * implement this interface to ensure interchangeability.
 */
interface Repository
{
    /**
     * Determine if an item exists in the cache.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Retrieve an item from the cache by key.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Store an item in the cache for a given number of seconds.
     *
     * @param string $key
     * @param mixed $value
     * @param int $ttl The Time To Live in seconds.
     * @return bool
     */
    public function put(string $key, mixed $value, int $ttl): bool;

    /**
     * Get an item from the cache, or execute the given Closure and store the result.
     *
     * @param string $key
     * @param int $ttl
     * @param \Closure $callback
     * @return mixed
     */
    public function remember(string $key, int $ttl, \Closure $callback): mixed;

    /**
     * Remove an item from the cache.
     *
     * @param string $key
     * @return bool
     */
    public function forget(string $key): bool;
}
