<?php

namespace Core\Server;

use Core\Application;
use Psr\Log\LoggerInterface;

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
     * This allows the worker to start faster.
     */
    public function initializePools(bool $isTaskWorker): void
    {
        \Swoole\Coroutine::create(function () use ($isTaskWorker) {
            $workerType = $isTaskWorker ? 'TaskWorker' : 'Worker';

            // Lặp qua các loại pool đã định nghĩa trong config (database, redis, ...)
            foreach ($this->poolsConfig as $poolType => $typeConfig) {
                if (!empty($typeConfig['enabled'])) {
                    $this->initializePoolType($poolType, $typeConfig, $isTaskWorker, $workerType);
                }
            }
        });
    }

    /**
     * Closes all connection pools.
     * This should be called when a worker stops.
     */
    public function closePools(): void
    {
        // Đóng tất cả các pool đã được khởi tạo
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

        foreach ($typeConfig['connections'] ?? [] as $name => $connectionConfig) {
            // Triển khai cơ chế retry khi khởi tạo pool
            $maxRetries = 3;
            $retryDelay = 1; // seconds

            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                try {
                    // Hợp nhất cấu hình mặc định và cấu hình riêng của connection
                    $finalConfig = array_replace_recursive($defaults, $connectionConfig);

                    $connectionDetails = $this->app->make('config')->get("{$configPrefix}.{$name}");
                    if (!$connectionDetails) {
                        throw new \InvalidArgumentException("Connection '{$name}' for pool type '{$poolType}' not found at config key '{$configPrefix}.{$name}'.");
                    }

                    $poolSizeKey = $isTaskWorker ? 'task_worker_pool_size' : 'worker_pool_size';
                    $poolSize = $finalConfig[$poolSizeKey] ?? 10;

                    $heartbeat = $finalConfig['heartbeat'] ?? null;
                    $circuitBreakerConfig = $finalConfig['circuit_breaker'] ?? [];

                    // Gọi phương thức init một cách linh động
                    $poolClass::init($connectionDetails, $poolSize, $circuitBreakerConfig, $this->app, $heartbeat, $name);

                    // Lưu lại class đã được khởi tạo để đóng lại sau
                    $this->initializedPoolClasses[] = $poolClass;

                    $this->logger->debug("Pool '{$name}' of type '{$poolType}' initialized for {$workerType}", ['size' => $poolSize]);
                    break; // Thoát khỏi vòng lặp nếu thành công
                } catch (\Throwable $e) {
                    $this->logger->warning("Attempt {$attempt}/{$maxRetries}: Failed to initialize pool '{$name}' of type '{$poolType}'. Retrying in {$retryDelay}s...", ['error' => $e->getMessage()]);
                    if ($attempt < $maxRetries) {
                        \Swoole\Coroutine::sleep($retryDelay);
                        $retryDelay *= 2; // Exponential backoff
                    } else {
                        $this->logger->error("Failed to initialize pool '{$name}' of type '{$poolType}' after {$maxRetries} attempts.", ['exception' => $e]);
                    }
                }
            }
        }
    }
}
