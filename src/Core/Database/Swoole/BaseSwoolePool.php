<?php

namespace Core\Database\Swoole;

use Ackintosh\Ganesha;
use Core\Application;
use Core\Debug\DebugManager;
use Core\Debug\Proxy\DebugPdoProxy;
use Core\Debug\Proxy\DebugRedisProxy;
use Core\Exceptions\ServiceUnavailableException;
use Core\Server\CircuitBreakerFactory;
use Swoole\Coroutine\Channel;
use Throwable;

abstract class BaseSwoolePool
{
    /** @var array<string, Channel> */
    protected static array $pools = [];

    /** @var array<string, string> */
    protected static array $aliases = [];

    /** @var array<string, array> */
    protected static array $configs = [];

    /** @var array<string, Ganesha> */
    protected static array $breakers = [];

    /** @var array<string, int> */
    protected static array $connectionCounts = [];

    protected static ?Application $app = null;

    /**
     * Initializes a connection pool for a specific service.
     */
    public static function init(array $config, int $poolSize, array $circuitBreakerConfig, Application $app, ?int $heartbeat = 60, string $name = 'default'): void
    {
        static::$app = $app;
        static::$configs[$name] = array_merge($config, [
            'pool_size' => $poolSize,
            'heartbeat' => $heartbeat,
        ]);
        static::$pools[$name] = new Channel($poolSize);
        static::$connectionCounts[$name] = 0;

        if (!empty($circuitBreakerConfig['enabled'])) {
            static::$breakers[$name] = CircuitBreakerFactory::create($circuitBreakerConfig, $app, static::class . '_' . $name);
        }

        for ($i = 0; $i < $poolSize; $i++) {
            $connection = static::createConnection($name);
            if ($connection) {
                static::$pools[$name]->push($connection);
                static::$connectionCounts[$name]++;
            }
        }
    }

    /**
     * Get a connection from the specified pool.
     *
     * @throws ServiceUnavailableException
     * @throws Throwable
     */
    public static function get(string $name = 'default', int $timeout = 3): mixed
    {
        $resolvedName = static::$aliases[$name] ?? $name;

        $breaker = static::$breakers[$resolvedName] ?? null;

        if ($breaker && !$breaker->isAvailable($resolvedName)) {
            throw new ServiceUnavailableException("Service '{$name}' (via '{$resolvedName}') is unavailable (Circuit Breaker is open).");
        }

        $pool = static::$pools[$resolvedName] ?? null;
        if (!$pool) {
            throw new \RuntimeException("Connection pool '{$name}' (via '{$resolvedName}') has not been initialized.");
        }

        try {
            // Try to get a connection for a limited number of attempts to avoid infinite loops
            $attempts = 0;
            $maxAttempts = ($pool->capacity * 2) + 1; // Allow for retries if connections are stale

            while ($attempts < $maxAttempts) {
                $attempts++;

                // If there are idle connections, try to use one
                if (!$pool->isEmpty()) {
                    $rawConnection = $pool->pop(0.001);
                    if ($rawConnection && static::ping($rawConnection, $resolvedName)) {
                        $breaker?->success($resolvedName);
                        return static::wrapConnectionForDebug($rawConnection);
                    }

                    // Connection is stale or invalid, discard it
                    if ($rawConnection) { // @phpstan-ignore-line
                        $breaker?->failure($resolvedName);
                        if (isset(static::$connectionCounts[$resolvedName])) {
                            static::$connectionCounts[$resolvedName] = max(0, static::$connectionCounts[$resolvedName] - 1);
                        }
                        unset($rawConnection); // Explicitly destroy the object
                    }
                }

                // If pool is not full, try to create a new connection
                if (static::$connectionCounts[$resolvedName] < $pool->capacity) {
                    if ($newConnection = static::createConnection($resolvedName)) {
                        static::$connectionCounts[$resolvedName]++;
                        $breaker?->success($resolvedName);
                        return static::wrapConnectionForDebug($newConnection);
                    }
                }

                if ($pool->isEmpty() && static::$connectionCounts[$resolvedName] < $pool->capacity) {
                    \Swoole\Coroutine::sleep(0.01);
                } elseif (!$pool->isEmpty()) {
                }
            }

            // Final attempt with full timeout if all else fails
            $rawConnection = $pool->pop($timeout); // This will throw if timeout is reached and pool is empty
            if ($rawConnection && static::ping($rawConnection, $resolvedName)) {
                return static::wrapConnectionForDebug($rawConnection);
            }

            throw new \RuntimeException("Failed to get a valid connection from pool '{$name}' after {$maxAttempts} attempts.");
        } catch (Throwable $e) {
            $breaker?->failure($resolvedName);
            throw $e;
        }
    }

    /**
     * Executes a callback with a connection from the pool and automatically returns it.
     * This is the recommended, safe way to use the connection pool.
     *
     * @param callable $callback The function to execute, which receives the connection as its only argument.
     * @param string $name The name of the connection pool.
     * @param int $timeout The timeout in seconds to wait for a connection.
     * @return mixed The return value of the callback.
     * @throws Throwable
     */
    public static function withConnection(callable $callback, string $name = 'default', int $timeout = 3): mixed
    {
        $connection = null;
        try {
            $connection = static::get($name, $timeout);
            return $callback($connection);
        } finally {
            if ($connection) {
                static::put($connection, $name);
            }
        }
    }

    /**
     * Return a connection to the pool.
     */
    public static function put(mixed $connection, string $name = 'default'): void
    {
        $resolvedName = static::$aliases[$name] ?? $name;

        $pool = static::$pools[$resolvedName] ?? null;
        $rawConnection = static::unwrapConnection($connection);

        if ($pool && !$pool->isFull()) {
            // Luôn put raw connection trở lại pool
            if (static::isValid($rawConnection)) {
                $pool->push($rawConnection);
            } else {
                // Nếu connection không hợp lệ (ví dụ: đang trong transaction), hủy nó
                static::$connectionCounts[$resolvedName]--;
                // Hủy đối tượng connection để đóng kết nối vật lý
                unset($connection);
            }
        }
    }

    /**
     * Close all connections and clear pools.
     */
    public static function close(): void
    {
        foreach (static::$pools as $name => $pool) {
            while (!$pool->isEmpty()) {
                $connection = $pool->pop(0.001);
                if ($connection) {
                    unset($connection);
                }
            }
            $pool->close();
        }
        static::$pools = [];
        static::$configs = [];
        static::$breakers = [];
        static::$aliases = [];
        static::$connectionCounts = [];
    }

    /**
     * This method is not implemented for this pool type.
     */
    public static function warmup(): void
    {
        // The pool is already warmed up during init()
    }

    /**
     * This method is not implemented for this pool type.
     */
    public static function releaseUnmanaged(): void
    {
        // This pool design (get/put) does not leak connections in the same way
        // as the fiber-aware managers, so this is a no-op.
    }

    /**
     * Checks if a specific connection pool has been initialized.
     *
     * @param string $name The name of the pool.
     * @return bool True if the pool is initialized, false otherwise.
     */
    public static function isInitialized(string $name = 'default'): bool
    {
        return isset(static::$pools[$name]);
    }

    /**
     * Registers an alias for an existing connection pool.
     *
     * @param string $aliasName The new alias name (e.g., 'cache').
     * @param string $targetName The name of the existing pool to alias (e.g., 'default').
     */
    public static function registerAlias(string $aliasName, string $targetName): void
    {
        if (!isset(static::$pools[$targetName])) {
            static::$app->make(\Psr\Log\LoggerInterface::class)
                ->warning("Cannot create alias '{$aliasName}' because target pool '{$targetName}' does not exist.");
            return;
        }
        static::$aliases[$aliasName] = $targetName;
    }

    /**
     * Resets all circuit breakers associated with this pool type.
     * This is useful in development environments after a hot-reload.
     */
    public static function resetCircuitBreakers(): void
    {
        foreach (static::$breakers as $breaker) {
            $breaker->reset();
        }
        static::$app?->make(\Psr\Log\LoggerInterface::class)->debug('Circuit breakers for ' . static::class . ' have been reset.');
    }

    /**
     * Abstract method to create a new connection.
     *
     * @param string $name The name of the connection configuration.
     * @return mixed The created connection object or false on failure.
     */
    abstract protected static function createConnection(string $name): mixed;

    /**
     * Abstract method to check if a connection is still alive.
     *
     * @param mixed $connection The connection to check.
     * @param string $name The resolved name of the pool.
     * @return bool True if alive, false otherwise.
     */
    abstract protected static function ping(mixed $rawConnection, string $name): bool;

    /**
     * Checks if the connection is in a valid state to be returned to the pool.
     * For example, a PDO connection should not be in an open transaction.
     *
     * @param mixed $connection
     * @return bool
     */
    protected static function isValid(mixed $connection): bool
    {
        return true;
    }

    /**
     * Wraps a raw connection in a debug proxy if debugging is enabled.
     */
    protected static function wrapConnectionForDebug(mixed $connection): mixed
    {
        if (
            static::$app->bound(DebugManager::class) &&
            ($debugManager = static::$app->make(DebugManager::class)) &&
            $debugManager->isEnabled()
        ) {
            if ($connection instanceof \PDO) {
                return new DebugPdoProxy($connection, $debugManager);
            }
            if ($connection instanceof \Redis) {
                return new DebugRedisProxy($connection, $debugManager);
            }
        }

        return $connection;
    }

    /**
     * Gets the original raw connection from a potential proxy object.
     */
    protected static function unwrapConnection(mixed $connection): mixed
    {
        if ($connection instanceof DebugPdoProxy) {
            return $connection->getOriginalConnection();
        }
        if ($connection instanceof DebugRedisProxy) {
            return $connection->getOriginalConnection();
        }
        return $connection;
    }
}
