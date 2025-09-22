<?php

namespace Core\Database\Swoole;

use Ackintosh\Ganesha;
use Core\Application;
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
        if (isset(static::$pools[$name])) {
            return;
        }

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
            $connection = $pool->pop($timeout);

            if (!static::ping($connection, $resolvedName)) {
                static::$connectionCounts[$resolvedName]--;
                $newConnection = static::createConnection($resolvedName);
                if ($newConnection) {
                    static::$connectionCounts[$resolvedName]++;
                    return $newConnection;
                }
                throw new \RuntimeException("Failed to create a new connection for pool '{$resolvedName}' after a stale one was found.");
            }

            $breaker?->success($resolvedName);
            return $connection;
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
        if ($pool && !$pool->isFull()) {
            if (static::isValid($connection)) {
                $pool->push($connection);
            } else {
                static::$connectionCounts[$resolvedName]--;
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
    abstract protected static function ping(mixed $connection, string $name): bool;

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
}
