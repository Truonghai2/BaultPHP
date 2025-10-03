<?php

namespace Core\Server;

use Core\Application;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine\Barrier;

/**
 * Manages the initialization and shutdown of database and Redis connection pools
 * for Swoole workers. This class centralizes pool management logic, keeping the
 * main SwooleServer class cleaner and more focused.
 */
class ConnectionPoolManager
{
    protected array $poolsConfig;

    /** @var string[] */
    protected array $initializedPoolClasses = [];

    public function __construct(
        protected Application $app,
        protected LoggerInterface $logger,
        array $serverConfig,
    ) {
        // Đọc cấu trúc config 'pools' mới
        $this->poolsConfig = $serverConfig['pools'] ?? [];
    }

    /**
     * Initializes all configured connection pools in a non-blocking coroutine.
     * This method registers its initialization logic into a barrier to ensure
     * the worker process waits for it to complete.
     */
    public function initializePools(bool $isTaskWorker): void
    {
        $workerType = $isTaskWorker ? 'TaskWorker' : 'Worker';

        foreach ($this->poolsConfig as $poolType => $typeConfig) {
            if (!empty($typeConfig['enabled'])) {
                $this->initializePoolType($poolType, $typeConfig, $isTaskWorker, $workerType);
            }
        }
    }

    /**
     * Closes all connection pools.
     * This should be called when a worker stops.
     */
    public function closePools(): void
    {
        foreach (array_unique($this->initializedPoolClasses) as $poolClass) {
            try {
                $poolClass::close();
                $this->logger->debug("Pool class {$poolClass} closed.");
            } catch (\Throwable $e) {
                $this->logger->error("Failed to close pool class {$poolClass}: " . $e->getMessage());
            }
        }
        $this->initializedPoolClasses = [];
    }

    /**
     * Khởi tạo tất cả các connection cho một loại pool cụ thể (vd: 'database').
     */
    private function initializePoolType(string $poolType, array $typeConfig, bool $isTaskWorker, string $workerType): void
    {
        $poolClass = $typeConfig['class'];
        $configPrefix = $typeConfig['config_prefix'];
        $defaults = $typeConfig['defaults'] ?? [];
        $connections = $typeConfig['connections'] ?? [];

        foreach ($typeConfig['connections'] ?? [] as $name => $connectionConfig) {
            if (!empty($connectionConfig['alias'])) {
                $this->logger->debug("Skipping alias '{$name}' in initial pool creation.");
                continue;
            }

            $finalConfig = array_replace_recursive($defaults, $connectionConfig);

            $retryConfig = $finalConfig['retry'] ?? [];
            $maxRetries = (int) ($retryConfig['max_attempts'] ?? 3);
            $initialDelayMs = (int) ($retryConfig['initial_delay_ms'] ?? 1000);
            $backoffMultiplier = (float) ($retryConfig['backoff_multiplier'] ?? 2.0);
            $maxDelayMs = (int) ($retryConfig['max_delay_ms'] ?? 30000);

            $currentDelayMs = $initialDelayMs;

            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                try {
                    $connectionName = $name;

                    $connectionDetails = $this->app->make('config')->get("{$configPrefix}.{$connectionName}");
                    if (!$connectionDetails) {
                        throw new \InvalidArgumentException("Connection '{$connectionName}' (for '{$name}') for pool type '{$poolType}' not found at config key '{$configPrefix}.{$connectionName}'.");
                    }
                    $poolSizeKey = $isTaskWorker ? 'task_worker_pool_size' : 'worker_pool_size';
                    $poolSize = (int) ($finalConfig[$poolSizeKey] ?? 10);

                    $workerCount = (int) ($this->app->make('config')->get('server.swoole.worker_num') ?: swoole_cpu_num());
                    $adjustedPoolSize = (int) ceil($poolSize / max(1, $workerCount));
                    $poolSize = max(1, $adjustedPoolSize);

                    $heartbeat = $finalConfig['heartbeat'] ?? null;
                    $circuitBreakerConfig = $finalConfig['circuit_breaker'] ?? [];

                    $poolClass::init($connectionDetails, $poolSize, $circuitBreakerConfig, $this->app, $heartbeat, $name);

                    $this->initializedPoolClasses[] = $poolClass;

                    $this->logger->debug("Pool '{$name}' of type '{$poolType}' initialized for {$workerType}", ['size' => $poolSize]);
                    break;
                } catch (\Throwable $e) {
                    $this->logger->warning("Attempt {$attempt}/{$maxRetries}: Failed to initialize pool '{$name}' of type '{$poolType}'. Retrying in {$currentDelayMs}ms...", ['error' => $e->getMessage()]);
                    if ($attempt < $maxRetries) {
                        \Swoole\Coroutine::sleep($currentDelayMs / 1000);
                        $currentDelayMs = min($maxDelayMs, (int)($currentDelayMs * $backoffMultiplier));
                    } else {
                        $this->logger->error("Failed to initialize pool '{$name}' of type '{$poolType}' after {$maxRetries} attempts.", ['exception' => $e]);
                    }
                }
            }
        }
        foreach ($connections as $name => $connectionConfig) {
            if (!empty($connectionConfig['alias'])) {
                $aliasTarget = $connectionConfig['alias'];
                $this->logger->debug("Registering '{$name}' as an alias for pool '{$aliasTarget}'.");
                $poolClass::registerAlias($name, $aliasTarget);
            }
        }
    }
}
