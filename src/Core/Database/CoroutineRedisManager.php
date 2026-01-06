<?php

namespace Core\Database;

use Core\Database\Swoole\SwooleRedisPool;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine;

/**
 * Manages the lifecycle of a Redis connection within a single coroutine.
 */
class CoroutineRedisManager
{
    /**
     * The base key used to store connections in the coroutine context.
     */
    protected const CONTEXT_KEY_PREFIX = 'redis_connection_';

    protected LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Get a Redis connection for the current coroutine.
     *
     * @param string|null $name The name of the connection pool to use. If null, the default will be used.
     * @return \Swoole\Coroutine\Client|\Redis
     * @throws \RuntimeException If the Redis pool is not initialized
     */
    public function get(string $name = null): \Swoole\Coroutine\Client|\Redis
    {
        $name ??= 'default';
        $contextKey = self::CONTEXT_KEY_PREFIX . $name;

        $cid = Coroutine::getCid();
        $context = Coroutine::getContext($cid);

        if (isset($context[$contextKey])) {
            $this->logger->debug('Reusing Redis connection from coroutine context.', ['cid' => $cid, 'connection' => $name]);
            return $context[$contextKey];
        }

        if (!SwooleRedisPool::isInitialized($name)) {
            $this->logger->warning("Redis pool '{$name}' is not initialized. Cannot get connection.", ['cid' => $cid]);
            throw new \RuntimeException("Redis pool '{$name}' is not initialized. Ensure the pool is configured and enabled in config/server.php");
        }

        $poolStats = method_exists(SwooleRedisPool::class, 'getAllStats') ? (SwooleRedisPool::getAllStats()[$name] ?? null) : null;
        $this->logger->debug('Fetching new Redis connection from pool for coroutine.', ['cid' => $cid, 'connection' => $name, 'pool_stats' => $poolStats]);
        $connection = SwooleRedisPool::get($name);
        $context[$contextKey] = $connection;

        Coroutine::defer(function () use ($connection, $name, $cid) {
            $this->release($connection, $name, $cid);
        });

        return $connection;
    }

    /**
     * Release a Redis connection back to the pool.
     */
    public function release(\Swoole\Coroutine\Client|\Redis $connection, string $name, int $cid): void
    {
        SwooleRedisPool::put($connection, $name);
        $poolStats = method_exists(SwooleRedisPool::class, 'getAllStats') ? (SwooleRedisPool::getAllStats()[$name] ?? null) : null;
        $this->logger->debug('Released Redis connection back to pool.', ['cid' => $cid, 'connection' => $name, 'pool_stats' => $poolStats]);
    }
}
