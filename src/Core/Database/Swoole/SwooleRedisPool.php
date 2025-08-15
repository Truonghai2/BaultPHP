<?php

namespace Core\Database\Swoole;

use Ackintosh\Ganesha;
use Core\Application;
use Core\Exceptions\ServiceUnavailableException;
use Core\Server\CircuitBreakerFactory;
use RuntimeException;
use Swoole\Database\RedisConfig;
use Swoole\Database\RedisPool;

/**
 * Class SwooleRedisPool
 * A static wrapper for the Swoole RedisPool to make it globally accessible.
 */
class SwooleRedisPool
{
    /**
     * The Swoole RedisPool instance.
     */
    protected static ?RedisPool $pool = null;
    protected static ?Ganesha $ganesha = null;
    protected static bool $circuitBreakerEnabled = false;
    protected static string $serviceName = 'swoole-redis-pool';

    /**
     * Check if the connection pool has been initialized.
     */
    public static function isInitialized(): bool
    {
        return self::$pool !== null;
    }

    /**
     * Initialize the Swoole Redis connection pool.
     */
    public static function init(array $redisConfig, int $poolSize, array $circuitBreakerConfig, Application $app): void
    {
        if (self::$pool) {
            return;
        }

        $config = (new RedisConfig());
        $config = $config->withHost($redisConfig['host'] ?? '127.0.0.1')
            ->withPort($redisConfig['port'] ?? 6379)
            ->withDbIndex($redisConfig['database'] ?? 0)
            ->withTimeout($redisConfig['timeout'] ?? 1.0);

        if (!empty($redisConfig['password'])) {
            $config = $config->withAuth((string) $redisConfig['password']);
        }

        self::$pool = new RedisPool($config, $poolSize);

        // Khởi tạo Circuit Breaker với Ganesha
        if (!empty($circuitBreakerConfig['enabled'])) {
            self::$circuitBreakerEnabled = true;
            self::$ganesha = CircuitBreakerFactory::create($circuitBreakerConfig, $app);
        }
    }

    /**
     * Get a Redis connection from the pool.
     *
     * @return \Swoole\Coroutine\Client|\Redis
     * @throws ServiceUnavailableException|RuntimeException If the pool is not initialized or the service is down.
     */
    public static function get(): \Redis
    {
        if (!self::$pool) {
            throw new RuntimeException('Swoole Redis connection pool has not been initialized.');
        }

        if (self::$circuitBreakerEnabled) {
            try {
                // Use execute() to wrap the operation. It will automatically handle
                // success/failure and open/close the circuit.
                return self::$ganesha->execute(fn () => self::$pool->get());
            } catch (Ganesha\Exception\RejectedException $e) {
                // The circuit is open, so we fail fast.
                throw new ServiceUnavailableException('Redis service is currently unavailable (circuit is open).', 503, $e);
            }
        }

        // If circuit breaker is not enabled, get connection directly.
        return self::$pool->get();
    }

    /**
     * Put a Redis connection back into the pool.
     */
    public static function put(\Swoole\Coroutine\Client|\Redis|null $connection): void
    {
        if (self::$pool && $connection) {
            self::$pool->put($connection);
        }
    }

    /**
     * Close the Swoole Redis connection pool.
     */
    public static function close(): void
    {
        if (self::$pool) {
            self::$pool->close();
            self::$pool = null;
            self::$ganesha = null;
        }
    }

    /**
     * Gets the circuit breaker instance for monitoring purposes.
     *
     * @return Ganesha|null
     */
    public static function getCircuitBreaker(): ?Ganesha
    {
        return self::$ganesha;
    }

    /**
     * Gets the service name used by the circuit breaker.
     *
     * @return string
     */
    public static function getServiceName(): string
    {
        return self::$serviceName;
    }

    /**
     * Get statistics about the connection pool.
     *
     * @return array
     */
    public static function stats(): array
    {
        if (!self::isInitialized()) {
            return ['initialized' => false];
        }

        $poolSize = self::$pool->getPoolSize();
        $availableConnections = self::$pool->num();

        return [
            'initialized' => true,
            'pool_size' => $poolSize,
            'connections_in_pool' => $availableConnections,
            'connections_in_use' => $poolSize - $availableConnections,
            'circuit_breaker_enabled' => self::$circuitBreakerEnabled,
            'circuit_breaker_state' => self::$ganesha?->getState() ?? 'N/A',
        ];
    }
}
