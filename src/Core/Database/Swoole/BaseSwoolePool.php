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
        $breaker = static::$breakers[$name] ?? null;

        if ($breaker && !$breaker->isAvailable()) {
            throw new ServiceUnavailableException("Service '{$name}' is unavailable (Circuit Breaker is open).");
        }

        $pool = static::$pools[$name] ?? null;
        if (!$pool) {
            throw new \RuntimeException("Connection pool '{$name}' has not been initialized.");
        }

        try {
            // Lấy kết nối từ pool
            $connection = $pool->pop($timeout);

            // Kiểm tra heartbeat để đảm bảo kết nối còn sống
            if (!static::ping($connection)) {
                static::$connectionCounts[$name]--;
                // Đóng kết nối hỏng và thử tạo một kết nối mới để thay thế
                $newConnection = static::createConnection($name);
                if ($newConnection) {
                    static::$connectionCounts[$name]++;
                    return $newConnection; // Trả về kết nối mới
                }
                // Nếu không tạo được kết nối mới, báo lỗi
                throw new \RuntimeException("Failed to create a new connection for pool '{$name}' after a stale one was found.");
            }

            $breaker?->success();
            return $connection;
        } catch (Throwable $e) {
            $breaker?->failure();
            throw $e;
        }
    }

    /**
     * Return a connection to the pool.
     */
    public static function put(mixed $connection, string $name = 'default'): void
    {
        $pool = static::$pools[$name] ?? null;
        if ($pool && !$pool->isFull()) {
            // Trước khi trả vào pool, kiểm tra xem nó có hợp lệ không
            if (static::isValid($connection)) {
                $pool->push($connection);
            } else {
                // Nếu kết nối không hợp lệ (ví dụ: đang trong transaction lỗi), hủy nó đi
                static::$connectionCounts[$name]--;
                unset($connection); // Đóng kết nối
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
                    // Logic đóng kết nối cụ thể (PDO, Redis, ...)
                    unset($connection);
                }
            }
            $pool->close();
        }
        static::$pools = [];
        static::$configs = [];
        static::$breakers = [];
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
     * @return bool True if alive, false otherwise.
     */
    abstract protected static function ping(mixed $connection): bool;

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
