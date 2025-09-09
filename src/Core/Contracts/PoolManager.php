<?php

namespace Core\Contracts;

/**
 * Interface for services that manage connection pools and need to be closed gracefully.
 */
interface PoolManager
{
    /**
     * Close all connections in the pool(s).
     */
    public function close(): void;

    /**
     * Pre-warms the connection pool with a specified number of connections.
     */
    public function warmup(): void;
}
