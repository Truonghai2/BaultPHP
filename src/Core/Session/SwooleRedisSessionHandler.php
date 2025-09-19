<?php

namespace Core\Session;

use Core\Database\Swoole\SwooleRedisPool;
use Redis;
use SessionHandlerInterface;

/**
 * A Redis-based session handler compatible with Swoole's coroutine environment.
 *
 * This handler acquires a connection from the SwooleRedisPool for each operation
 * and releases it immediately, making it safe for concurrent requests.
 */
class SwooleRedisSessionHandler implements SessionHandlerInterface
{
    /**
     * The prefix for session keys in Redis.
     */
    private string $prefix = 'session:';

    /**
     * @param string $connectionName The name of the Redis pool connection.
     * @param int $lifetime The session lifetime in seconds.
     */
    public function __construct(
        private string $connectionName,
        private int $lifetime,
    ) {
    }

    /**
     * A helper method to execute a command within a get/put block.
     *
     * @param callable $callback The callback to execute with the Redis connection.
     * @return mixed
     */
    private function withConnection(callable $callback): mixed
    {
        $redis = null;
        try {
            $redis = SwooleRedisPool::get($this->connectionName);
            return $callback($redis);
        } finally {
            if ($redis) {
                SwooleRedisPool::put($redis, $this->connectionName);
            }
        }
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $sessionId): string|false
    {
        return $this->withConnection(function (Redis $redis) use ($sessionId) {
            $data = $redis->get($this->prefix . $sessionId);
            return $data === false ? '' : $data;
        });
    }

    public function write(string $sessionId, string $data): bool
    {
        return $this->withConnection(function (Redis $redis) use ($sessionId, $data) {
            return $redis->setex($this->prefix . $sessionId, $this->lifetime, $data);
        });
    }

    public function destroy(string $sessionId): bool
    {
        return (bool) $this->withConnection(function (Redis $redis) use ($sessionId) {
            return $redis->del($this->prefix . $sessionId);
        });
    }

    public function gc(int $max_lifetime): int|false
    {
        // Redis with EXPIRE/SETEX handles garbage collection automatically.
        return 0;
    }
}
