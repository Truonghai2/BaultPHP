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
            return;
        }

        $connectionName = $this->dbPoolConfig['connection'];
        $dbConfig = $this->app->make('config')->get("database.connections.{$connectionName}");
        $poolSize = $isTaskWorker ? ($this->dbPoolConfig['task_worker_pool_size'] ?? 10) : ($this->dbPoolConfig['worker_pool_size'] ?? 10);
        $heartbeat = $this->dbPoolConfig['heartbeat'] ?? 60;
        $circuitBreakerConfig = $this->dbPoolConfig['circuit_breaker'] ?? [];
        SwoolePdoPool::init($dbConfig, $poolSize, $heartbeat, $circuitBreakerConfig, $this->app);
        $this->logger->debug("Swoole PDO Pool initialized for {$workerType}");
    }

    private function initializeRedisPool(bool $isTaskWorker, string $workerType): void
    {
        if (empty($this->redisPoolConfig['enabled'])) {
            return;
        }

        $connectionName = $this->redisPoolConfig['connection'];
        $redisConfig = $this->app->make('config')->get("database.redis.{$connectionName}");
        $poolSize = $isTaskWorker ? ($this->redisPoolConfig['task_worker_pool_size'] ?? 10) : ($this->redisPoolConfig['worker_pool_size'] ?? 10);
        $circuitBreakerConfig = $this->redisPoolConfig['circuit_breaker'] ?? [];
        SwooleRedisPool::init($redisConfig, $poolSize, $circuitBreakerConfig, $this->app);
        $this->logger->debug("Swoole Redis Pool initialized for {$workerType}");
    }
}
