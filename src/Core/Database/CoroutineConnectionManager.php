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
     * The key used to store the connection in the coroutine context.
     */
    protected const CONTEXT_KEY = 'db_connection';
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
     * @return PDO|PDOProxy
     */
    public function get(): PDO|PDOProxy
    {
        $cid = Coroutine::getCid();
        $context = Coroutine::getContext($cid);

        if (isset($context[self::CONTEXT_KEY])) {
            $this->logger->debug('Reusing DB connection from coroutine context.', ['cid' => $cid]);
            return $context[self::CONTEXT_KEY];
        }

        // Connection does not exist for this coroutine, get a new one from the pool.
        $this->logger->debug('Fetching new DB connection from pool for coroutine.', ['cid' => $cid, 'pool_stats' => SwoolePdoPool::stats()]);
        $connection = SwoolePdoPool::get();

        // Store it in the context for subsequent calls within the same coroutine.
        $context[self::CONTEXT_KEY] = $connection;

        // CRITICAL: Defer the release of the connection.
        // This function will be executed automatically when the coroutine finishes,
        // ensuring the connection is always returned to the pool.
        Coroutine::defer(function () use ($connection, $cid) {
            $this->release($connection, $cid);
        });

        return $connection;
    }

    /**
     * Release a PDO connection back to the pool.
     */
    public function release(PDO|PDOProxy $connection, int $cid): void
    {
        SwoolePdoPool::put($connection);
        $this->logger->debug('Released DB connection back to pool.', ['cid' => $cid, 'pool_stats' => SwoolePdoPool::stats()]);
    }
}
