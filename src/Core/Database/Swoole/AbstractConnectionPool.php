<?php

namespace Core\Database\Swoole;

use Ackintosh\Ganesha;
use Core\Application;
use Core\Exceptions\ServiceUnavailableException;
use Core\Server\CircuitBreakerFactory;
use RuntimeException;
use Swoole\ConnectionPool;
use Throwable;

/**
 * An abstract base class for Swoole connection pools.
 * It centralizes common logic for initialization, circuit breaking, and stats.
 */
abstract class AbstractConnectionPool
{
    protected static ?Application $app = null;
    protected static ?ConnectionPool $pool = null;
    protected static ?Ganesha $ganesha = null;
    protected static bool $circuitBreakerEnabled = false;
    protected static string $serviceName = 'unknown';
    protected static int $poolSize = 0;

    /**
     * Initialize the connection pool.
     */
    abstract public static function init(array $config, int $poolSize, array $circuitBreakerConfig, Application $app, ?int $heartbeat = null): void;

    /**
     * Get a raw connection from the pool and verify it's alive.
     * @return mixed The connection object.
     * @throws Throwable If the connection is not healthy.
     */
    abstract protected static function getAndVerifyConnection(): mixed;

    /**
     * Check if the connection pool has been initialized.
     */
    public static function isInitialized(): bool
    {
        return static::$pool !== null;
    }

    /**
     * Get a connection from the pool, wrapped by the circuit breaker logic.
     *
     * @return mixed The connection object.
     * @throws ServiceUnavailableException|RuntimeException If the pool is not initialized or the service is down.
     */
    public static function get(): mixed
    {
        if (!static::isInitialized()) {
            throw new RuntimeException('Swoole ' . static::$serviceName . ' connection pool has not been initialized.');
        }

        if (static::$circuitBreakerEnabled && static::$ganesha) {
            if (!static::$ganesha->isAvailable(static::$serviceName)) {
                throw new ServiceUnavailableException(ucfirst(static::$serviceName) . ' service is currently unavailable (circuit is open).', 503);
            }

            try {
                $connection = static::getAndVerifyConnection();
                static::$ganesha->success(static::$serviceName);
                return $connection;
            } catch (Throwable $e) {
                static::$ganesha->failure(static::$serviceName);
                throw new ServiceUnavailableException('Failed to get a healthy ' . static::$serviceName . ' connection: ' . $e->getMessage(), 503, $e);
            }
        }

        return static::getAndVerifyConnection();
    }

    /**
     * Put a connection back into the pool.
     */
    public static function put(mixed $connection): void
    {
        if (static::$pool && $connection) {
            static::$pool->put($connection);
        }
    }

    /**
     * Close the connection pool.
     */
    public static function close(): void
    {
        if (static::$pool) {
            static::$pool->close();
            static::$pool = null;
            static::$ganesha = null;
            static::$circuitBreakerEnabled = false;
        }
    }

    /**
     * Gets the circuit breaker instance for monitoring purposes.
     */
    public static function getCircuitBreaker(): ?Ganesha
    {
        return static::$ganesha;
    }

    /**
     * Gets the service name used by the circuit breaker.
     */
    public static function getServiceName(): string
    {
        return static::$serviceName;
    }

    /**
     * Get statistics about the connection pool.
     */
    public static function stats(): array
    {
        if (!static::isInitialized()) {
            return ['initialized' => false];
        }

        $poolSize = static::$poolSize;
        $availableConnections = (static::$pool instanceof \Countable) ? count(static::$pool) : 0;

        return [
            'initialized' => true,
            'pool_size' => $poolSize,
            'connections_in_pool' => $availableConnections,
            'connections_in_use' => $poolSize - $availableConnections,
            'circuit_breaker_enabled' => static::$circuitBreakerEnabled,
            'circuit_breaker_state' => static::$ganesha ?
                (static::$ganesha->isAvailable(static::$serviceName) ? 'AVAILABLE (CLOSED or HALF_OPEN)' : 'UNAVAILABLE (OPEN)')
                : 'N/A',
        ];
    }

    /**
     * Helper to initialize the circuit breaker.
     */
    protected static function initializeCircuitBreaker(array $circuitBreakerConfig, Application $app): void
    {
        if (!empty($circuitBreakerConfig['enabled'])) {
            static::$circuitBreakerEnabled = true;
            static::$ganesha = CircuitBreakerFactory::create($circuitBreakerConfig, $app);
        } else {
            static::$circuitBreakerEnabled = false;
            static::$ganesha = null;
        }
    }
}
