<?php

namespace Core\Database\Swoole;

use Ackintosh\Ganesha;
use Core\Application;
use Core\Exceptions\RedisException;
use RuntimeException;
use Swoole\ConnectionPool;
use Swoole\Database\RedisConfig;
use Swoole\Database\RedisPool;
use Throwable;

/**
 * Class SwooleRedisPool
 * A static wrapper for the Swoole RedisPool to make it globally accessible.
 */
class SwooleRedisPool extends AbstractConnectionPool
{
    protected static ?ConnectionPool $pool = null;
    protected static ?Ganesha $ganesha = null;
    protected static bool $circuitBreakerEnabled = false;
    protected static string $serviceName = 'redis';
    protected static int $poolSize = 0;

    /**
     * Initialize the Swoole Redis connection pool.
     */
    public static function init(array $config, int $poolSize, array $circuitBreakerConfig, Application $app, ?int $heartbeat = null): void
    {
        if (self::isInitialized()) {
            return;
        }

        self::$poolSize = $poolSize;

        $swooleConfig = (new RedisConfig())
            ->withHost($config['host'] ?? '127.0.0.1')
            ->withPort($config['port'] ?? 6379)
            ->withDbIndex($config['database'] ?? 0)
            ->withTimeout($config['timeout'] ?? 1.0);

        if (!empty($config['password'])) {
            $swooleConfig = $swooleConfig->withAuth((string) $config['password']);
        }

        self::$pool = new RedisPool($swooleConfig, $poolSize);
        self::initializeCircuitBreaker($circuitBreakerConfig, $app);
    }

    /**
     * Get a raw connection from the pool and verify it's alive.
     * @return \Redis The connection object.
     * @throws Throwable If the connection is not healthy.
     */
    protected static function getAndVerifyConnection(): \Redis
    {
        if (!self::$pool) {
            throw new RuntimeException('Redis Pool is not available.');
        }

        /** @var \Redis $redis */
        $redis = self::$pool->get();

        try {
            $redis->ping();
        } catch (\RedisException $e) {
            $redis->close(); // Close the broken connection
            throw new RedisException('Redis connection ping failed.', 0, $e);
        }

        return $redis;
    }
}
