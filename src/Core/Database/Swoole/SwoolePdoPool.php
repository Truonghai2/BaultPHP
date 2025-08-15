<?php

namespace Core\Database\Swoole;

use Ackintosh\Ganesha;
use Core\Application;
use Core\Exceptions\ServiceUnavailableException;
use Core\Server\CircuitBreakerFactory;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine\Channel;
use WeakMap;

/**
 * Manages a pool of PDO connections for a Swoole server.
 *
 * This class implements a heartbeat mechanism to prevent "MySQL server has gone away" errors
 * by checking and refreshing stale connections.
 */
class SwoolePdoPool
{
    protected static ?Channel $pool = null;
    protected static array $config;
    protected static int $poolSize;
    protected static int $heartbeat;
    protected static int $currentConnections = 0;
    protected static ?Ganesha $circuitBreaker = null;
    protected static bool $circuitBreakerEnabled = false;
    protected static string $serviceName = 'database';

    /**
     * @var WeakMap<PDO, int> Stores the last used timestamp for each connection object.
     * WeakMap is used to avoid preventing the PDO object from being garbage collected.
     */
    protected static ?WeakMap $lastUsed = null;

    /**
     * Initializes the connection pool.
     *
     * @param array $config The database connection configuration.
     * @param int $poolSize The maximum number of connections in the pool.
     * @param int $heartbeat The idle time in seconds before a connection is checked.
     */
    public static function init(array $config, int $poolSize, int $heartbeat, array $circuitBreakerConfig, Application $app): void
    {
        if (self::isInitialized()) {
            return;
        }

        self::$config = $config;
        self::$poolSize = $poolSize;
        self::$heartbeat = $heartbeat;
        self::$pool = new Channel($poolSize);
        self::$lastUsed = new WeakMap();

        // Khởi tạo Circuit Breaker
        if (!empty($circuitBreakerConfig['enabled'])) {
            self::$circuitBreakerEnabled = true;
            self::$circuitBreaker = CircuitBreakerFactory::create($circuitBreakerConfig, $app);
        }

        $logger = $app->bound(LoggerInterface::class) ? $app->make(LoggerInterface::class) : null;

        for ($i = 0; $i < $poolSize; $i++) {
            try {
                $pdo = self::createConnectionInternal();
                self::put($pdo); // Use put() to correctly tag the timestamp
                self::$currentConnections++;
            } catch (PDOException $e) {
                // Log the failure but don't crash the worker. The pool will have fewer connections.
                // The circuit breaker will handle retries on subsequent `get()` calls.
                $logger?->error('Failed to create initial PDO connection for pool: ' . $e->getMessage());
            }
        }
    }

    /**
     * Returns a connection to the pool.
     *
     * @param PDO $pdo
     */
    public static function put(PDO $pdo): void
    {
        if (!self::isInitialized() || self::$pool->isFull()) {
            return; // Pool might have been closed or is full
        }

        // Tag the connection with the current timestamp before returning it to the pool.
        self::$lastUsed[$pdo] = time();
        self::$pool->push($pdo);
    }

    /**
     * Checks if the pool has been initialized.
     *
     * @return bool
     */
    public static function isInitialized(): bool
    {
        return self::$pool !== null;
    }

    /**
     * Closes the connection pool and all its connections.
     */
    public static function close(): void
    {
        if (!self::isInitialized()) {
            return;
        }

        while (!self::$pool->isEmpty()) {
            $pdo = self::$pool->pop(0.001);
            // PDO objects are closed when they are destructed.
        }

        self::$pool->close();
        self::$pool = null;
        self::$lastUsed = null;
        self::$currentConnections = 0;
    }

    /**
     * Gets a connection from the pool, wrapped by the circuit breaker logic.
     *
     * @return PDO
     * @throws ServiceUnavailableException if the circuit is open.
     * @throws \Throwable if getting a connection fails for other reasons.
     */
    public static function get(): PDO
    {
        if (!self::isInitialized()) {
            throw new \RuntimeException('SwoolePdoPool is not initialized.');
        }

        if (self::$circuitBreakerEnabled) {
            try {
                return self::$circuitBreaker->execute(fn () => self::getAndVerifyConnection());
            } catch (Ganesha\Exception\RejectedException $e) {
                throw new ServiceUnavailableException('Database service is currently unavailable (circuit is open).', 503, $e);
            }
        }

        // Fallback for when Circuit Breaker is disabled
        return self::getAndVerifyConnection();
    }

    /**
     * The core logic to get a connection from the channel and verify its health.
     *
     * @return PDO
     */
    private static function getAndVerifyConnection(): PDO
    {
        $pdo = self::$pool->pop();

        $lastUsedTime = self::$lastUsed[$pdo] ?? 0;
        if ((time() - $lastUsedTime) > self::$heartbeat) {
            try {
                $pdo->query('SELECT 1'); // Ping the connection
            } catch (PDOException $e) {
                // Ping failed, connection is dead.
                self::$currentConnections--;
                // Create a new connection. This will throw on failure, which is
                // caught by the circuit breaker's execute() block.
                $newPdo = self::createConnectionInternal();
                self::$currentConnections++;
                return $newPdo;
            }
        }

        // If we reach here, the connection from the pool is healthy.
        return $pdo;
    }

    /**
     * Creates a new PDO connection. This method is internal and should throw
     * a PDOException on failure, which will be caught by the circuit breaker.
     *
     * @return PDO
     * @throws PDOException
     */
    private static function createConnectionInternal(): PDO
    {
        $host = self::$config['write']['host'] ?? self::$config['host'] ?? '127.0.0.1';
        $dsn = sprintf(
            '%s:host=%s;port=%s;dbname=%s;charset=%s',
            self::$config['driver'] ?? 'mysql',
            $host,
            self::$config['port'] ?? '3306',
            self::$config['database'] ?? '',
            self::$config['charset'] ?? 'utf8mb4',
        );
        $options = (self::$config['options'] ?? []) + [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
        return new PDO($dsn, self::$config['username'] ?? '', self::$config['password'] ?? '', $options);
    }

    /**
     * Gets the circuit breaker instance for monitoring purposes.
     *
     * @return Ganesha|null
     */
    public static function getCircuitBreaker(): ?Ganesha
    {
        return self::$circuitBreaker;
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

        return [
            'initialized' => true,
            'pool_size' => self::$poolSize,
            'connections_in_pool' => self::$pool->length(),
            'total_connections_created' => self::$currentConnections,
            'heartbeat_seconds' => self::$heartbeat,
            'circuit_breaker_enabled' => self::$circuitBreakerEnabled,
            'circuit_breaker_state' => self::$circuitBreaker?->getState() ?? 'N/A',
        ];
    }
}
