<?php

namespace Core\Database;

use Core\Database\Swoole\SwoolePdoPool;
use PDO;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine;
use Swoole\Database\PDOProxy;

/**
 * Manages the lifecycle of a PDO connection within a single coroutine.
 * It ensures that a connection is fetched from the pool only once per coroutine
 * and is always returned to the pool when the coroutine finishes.
 */
class CoroutineConnectionManager
{
    /**
     * The base key used to store connections in the coroutine context.
     */
    protected const CONTEXT_KEY_PREFIX = 'db_connection_';

    protected LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Get a PDO connection for the current coroutine.
     *
     * If a connection has already been fetched for this coroutine, it will be returned.
     * Otherwise, a new connection is fetched from the pool, and its release is
     * deferred until the coroutine exits.
     *
     * @param string|null $name The name of the connection pool to use. If null, the default will be used.
     * @return PDO|PDOProxy
     */
    public function get(string $name = null): PDO|PDOProxy
    {
        $name ??= config('database.default', 'mysql');
        $contextKey = self::CONTEXT_KEY_PREFIX . $name;

        $cid = Coroutine::getCid();
        $context = Coroutine::getContext($cid);

        if (isset($context[$contextKey])) {
            // Reusing existing connection from coroutine context
            return $context[$contextKey];
        }

        // Fetch new connection from pool
        $connection = SwoolePdoPool::get($name);

        if ($connection === false) {
            throw new \RuntimeException("Failed to get a database connection from the pool for '{$name}'. The pool might be exhausted or the connection failed.");
        }

        $context[$contextKey] = $connection;

        Coroutine::defer(function () use ($connection, $name, $cid) {
            $this->release($connection, $name, $cid);
        });

        return $connection;
    }

    /**
     * Release a PDO connection back to the pool.
     */
    public function release(PDO|PDOProxy $connection, string $name, int $cid): void
    {
        SwoolePdoPool::put($connection, $name);
        // Connection released back to pool
    }

    /**
     * Get stats for a specific pool, handling API inconsistencies gracefully.
     *
     * @param string $name The name of the pool.
     * @return array|null
     */
    private function getPoolStats(string $name): ?array
    {
        if (method_exists(SwoolePdoPool::class, 'getAllStats')) {
            return SwoolePdoPool::getAllStats()[$name] ?? null;
        }

        return null;
    }
}
