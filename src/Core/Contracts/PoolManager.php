<?php

namespace Core\Contracts;

interface PoolManager
{
    /**
     * Get a connection from the pool.
     *
     * @param string|null $name
     * @return mixed
     */
    public function get(?string $name = null);

    /**
     * Return a connection to the pool.
     *
     * @param mixed $connection
     * @param string|null $name
     */
    public function put($connection, ?string $name = null): void;

    /**
     * Warm up the connection pools.
     */
    public function warmup(): void;

    /**
     * Close all connections in all pools.
     */
    public function close(): void;

    /**
     * Releases any connections that were checked out but not explicitly returned.
     */
    public function releaseUnmanaged(): void;
}
