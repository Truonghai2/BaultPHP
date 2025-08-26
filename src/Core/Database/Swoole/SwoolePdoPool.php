<?php

namespace Core\Database\Swoole;

use Ackintosh\Ganesha;
use Core\Application;
use Core\Debug\TraceablePdo;
use PDO;
use RuntimeException;
use Swoole\ConnectionPool;
use Swoole\Database\PDOConfig;
use Swoole\Database\PDOPool;
use Throwable;

/**
 * Manages a pool of PDO connections for a Swoole server.
 *
 * This class is a static wrapper around the native Swoole PDOPool, making it
 * globally accessible and integrating it with the application's circuit breaker.
 */
class SwoolePdoPool extends AbstractConnectionPool
{
    protected static ?ConnectionPool $pool = null;
    protected static ?Ganesha $ganesha = null;
    protected static bool $circuitBreakerEnabled = false;
    protected static string $serviceName = 'database';
    protected static int $poolSize = 0;

    /**
     * Initializes the connection pool.
     *
     * @param array $config The database connection configuration.
     */
    public static function init(array $config, int $poolSize, array $circuitBreakerConfig, Application $app, ?int $heartbeat = 60): void
    {
        if (self::isInitialized()) {
            return;
        }

        self::$app = $app;
        self::$poolSize = $poolSize;

        $host = $config['write']['host'] ?? $config['host'] ?? '127.0.0.1';

        $swooleConfig = (new PDOConfig())
            ->withDriver($config['driver'] ?? 'mysql')
            ->withHost($host)
            ->withPort($config['port'] ?? 3306)
            ->withDbName($config['database'] ?? '')
            ->withCharset($config['charset'] ?? 'utf8mb4')
            ->withUsername($config['username'] ?? '')
            ->withPassword($config['password'] ?? '');

        self::$pool = new PDOPool($swooleConfig, $poolSize);

        if (property_exists(self::$pool, 'heartbeat')) {
            self::$pool->heartbeat = (float) $heartbeat;
        }
        self::initializeCircuitBreaker($circuitBreakerConfig, $app);
    }

    /**
     * Get a raw connection from the pool and verify it's alive.
     * @return \PDO|\Swoole\Database\PDOProxy The connection object.
     * @throws Throwable If the connection is not healthy.
     */
    protected static function getAndVerifyConnection(): mixed
    {
        if (!self::$pool) {
            throw new RuntimeException('PDO Pool is not available.');
        }

        /** @var PDO $connection */
        $connection = self::$pool->get();

        // Nếu debug được bật, wrap connection trong TraceablePdo
        if (config('debug.enabled', false) && self::$app->bound(\Core\Debug\DebugManager::class)) {
            /** @var \Core\Debug\DebugManager $debugManager */
            $debugManager = self::$app->make(\Core\Debug\DebugManager::class);
            if ($debugManager->isEnabled()) {
                $connection = new TraceablePdo($connection, $debugManager);
            }
        }

        try {
            // A simple, low-overhead query to check if the connection is still alive.
            $connection->getAttribute(\PDO::ATTR_SERVER_INFO);
        } catch (Throwable $e) {
            // If the connection is dead, we don't put it back in the pool.
            // The pool will create a new one when needed.
            throw new RuntimeException('PDO connection is no longer alive.', 0, $e);
        }

        return $connection;
    }
}
