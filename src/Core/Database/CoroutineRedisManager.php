<?php

namespace Core\Database;

use Core\Database\Swoole\SwooleRedisPool;
use Swoole\Coroutine;

/**
 * Manages the lifecycle of a Redis connection within a single coroutine.
 */
class CoroutineRedisManager
{
    /**
     * The key used to store the connection in the coroutine context.
     */
    protected const CONTEXT_KEY = 'redis_connection';
    protected LoggerInterface $logger;

    /**
     * Get a Redis connection for the current coroutine.
     *
     * @return \Swoole\Coroutine\Client|\Redis
     */
    public function get(): \Swoole\Coroutine\Client|\Redis
    {
        $cid = Coroutine::getCid();
        $context = Coroutine::getContext();

        if (isset($context[self::CONTEXT_KEY])) {
            $this->logger->debug('Reusing Redis connection from coroutine context.', ['cid' => $cid]);
            return $context[self::CONTEXT_KEY];
        }

        $this->logger->debug('Fetching new Redis connection from pool for coroutine.', ['cid' => $cid, 'pool_stats' => SwooleRedisPool::stats()]);
        $connection = SwooleRedisPool::get();
        $context[self::CONTEXT_KEY] = $connection;

        Coroutine::defer(function () use ($connection, $cid) {
            $this->release($connection, $cid);
        });

        return $connection;
    }

    /**
     * Release a Redis connection back to the pool.
     */
    public function release(\Swoole\Coroutine\Client|\Redis $connection, int $cid): void
    {
        SwooleRedisPool::put($connection);
        $this->logger->debug('Released Redis connection back to pool.', ['cid' => $cid, 'pool_stats' => SwooleRedisPool::stats()]);
    }
}
