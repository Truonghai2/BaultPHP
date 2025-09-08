<?php

namespace Core\Server;

use Core\Application;
use Core\Database\Swoole\SwoolePdoPool;
use Core\Database\Swoole\SwooleRedisPool;
use Psr\Log\LoggerInterface;

/**
 * Manages the initialization and shutdown of database and Redis connection pools
 * for Swoole workers. This class centralizes pool management logic, keeping the
 * main SwooleServer class cleaner and more focused.
 */
class ConnectionPoolManager
{
    protected array $dbPoolConfig;
    protected array $redisPoolConfig;

    public function __construct(
        protected Application $app,
        protected LoggerInterface $logger,
        array $serverConfig,
    ) {
        $this->dbPoolConfig = $serverConfig['db_pool'] ?? [];
        $this->redisPoolConfig = $serverConfig['redis_pool'] ?? [];
    }

    /**
     * Initializes all configured connection pools in a non-blocking coroutine.
     * This allows the worker to start faster.
     */
    public function initializePools(bool $isTaskWorker): void
    {
        \Swoole\Coroutine::create(function () use ($isTaskWorker) {
            $workerType = $isTaskWorker ? 'TaskWorker' : 'Worker';
            try {
                $this->initializeDbPool($isTaskWorker, $workerType);
                $this->initializeRedisPool($isTaskWorker, $workerType);
            } catch (\Throwable $e) {
                $this->logger->error(
                    "Failed to initialize connection pools for {$workerType}: " . $e->getMessage(),
                    ['exception' => $e],
                );
            }
        });
    }

    /**
     * Closes all connection pools.
     * This should be called when a worker stops.
     */
    public function closePools(): void
    {
        SwoolePdoPool::close();
        SwooleRedisPool::close();
    }

    private function initializeDbPool(bool $isTaskWorker, string $workerType): void
    {
        if (empty($this->dbPoolConfig['enabled'])) {
            $this->logger->debug('DB Pool is disabled, skipping initialization.');
            return;
        }

        // Iterate over all configured pools and initialize them by name
        foreach ($this->dbPoolConfig['pools'] ?? [] as $name => $poolConfig) {
            try {
                $dbConfig = $this->app->make('config')->get("database.connections.{$name}");
                if (!$dbConfig) {
                    throw new \InvalidArgumentException("Database connection '{$name}' is not defined in config/database.php.");
                }
                $poolSize = $isTaskWorker ? ($poolConfig['task_worker_pool_size'] ?? $poolConfig['max_connections'] ?? 10) : ($poolConfig['worker_pool_size'] ?? $poolConfig['max_connections'] ?? 10);
                $heartbeat = $poolConfig['heartbeat'] ?? 60;
                $circuitBreakerConfig = $poolConfig['circuit_breaker'] ?? [];
                SwoolePdoPool::init($dbConfig, $poolSize, $circuitBreakerConfig, $this->app, $heartbeat, $name);
                $this->logger->debug("Swoole PDO Pool '{$name}' initialized for {$workerType}", ['size' => $poolSize]);
            } catch (\Throwable $e) {
                $this->logger->error("Failed to initialize DB pool '{$name}': " . $e->getMessage());
            }
        }
    }

    private function initializeRedisPool(bool $isTaskWorker, string $workerType): void
    {
        if (empty($this->redisPoolConfig['enabled'])) {
            $this->logger->debug('Redis Pool is disabled, skipping initialization.');
            return;
        }

        // Iterate over all configured pools and initialize them by name
        foreach ($this->redisPoolConfig['pools'] ?? [] as $name => $poolConfig) {
            try {
                $redisConfig = $this->app->make('config')->get("database.redis.{$name}");
                if (!$redisConfig) {
                    throw new \InvalidArgumentException("Redis connection '{$name}' is not defined in config/database.php.");
                }
                $poolSize = $isTaskWorker ? ($poolConfig['task_worker_pool_size'] ?? $poolConfig['max_connections'] ?? 10) : ($poolConfig['worker_pool_size'] ?? $poolConfig['max_connections'] ?? 10);
                $circuitBreakerConfig = $poolConfig['circuit_breaker'] ?? [];

                SwooleRedisPool::init($redisConfig, $poolSize, $circuitBreakerConfig, $this->app, null, $name);
                $this->logger->debug("Swoole Redis Pool '{$name}' initialized for {$workerType}", ['size' => $poolSize]);
            } catch (\Throwable $e) {
                $this->logger->error("Failed to initialize Redis pool '{$name}': " . $e->getMessage());
            }
        }
    }
}
