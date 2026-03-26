<?php

declare(strict_types=1);

namespace Core\Server;

use Psr\Log\LoggerInterface;
use Swoole\Http\Server as SwooleHttpServer;
use Swoole\Timer;

/**
 * Monitors health of Swoole workers and alerts on issues.
 * 
 * Tracks worker statistics and can trigger automatic restarts
 * if workers become unhealthy.
 */
class WorkerHealthMonitor
{
    private array $workerStats = [];
    private ?int $timerId = null;
    private int $checkInterval = 10000; // 10 seconds

    public function __construct(
        private SwooleHttpServer $server,
        private LoggerInterface $logger,
        private array $config = []
    ) {
        $this->checkInterval = $config['check_interval_ms'] ?? 10000;
    }

    /**
     * Start monitoring workers.
     *
     * @return void
     */
    public function startMonitoring(): void
    {
        $this->timerId = Timer::tick($this->checkInterval, function() {
            $this->checkAllWorkers();
        });

        $this->logger->info('Worker health monitoring started', [
            'check_interval_ms' => $this->checkInterval,
        ]);
    }

    /**
     * Stop monitoring.
     *
     * @return void
     */
    public function stopMonitoring(): void
    {
        if ($this->timerId !== null) {
            Timer::clear($this->timerId);
            $this->timerId = null;
            
            $this->logger->info('Worker health monitoring stopped');
        }
    }

    /**
     * Check health of all workers.
     *
     * @return void
     */
    private function checkAllWorkers(): void
    {
        $stats = $this->server->stats();
        
        if (!$stats) {
            $this->logger->warning('Failed to get server stats');
            return;
        }

        $workerNum = $stats['worker_num'] ?? 0;
        
        for ($workerId = 0; $workerId < $workerNum; $workerId++) {
            $health = $this->checkWorkerHealth($workerId, $stats);
            
            $this->workerStats[$workerId] = $health;
            
            // Log and handle unhealthy workers
            if ($health['status'] === 'unhealthy') {
                $this->logger->error("Worker #{$workerId} is unhealthy", $health);
                $this->handleUnhealthyWorker($workerId, $health);
            } elseif ($health['status'] === 'degraded') {
                $this->logger->warning("Worker #{$workerId} is degraded", $health);
            }
        }
    }

    /**
     * Check health of a specific worker.
     *
     * @param int $workerId
     * @param array $serverStats
     * @return array
     */
    private function checkWorkerHealth(int $workerId, array $serverStats): array
    {
        // Calculate worker-specific metrics
        $totalRequests = $serverStats['request_count'] ?? 0;
        $workerNum = $serverStats['worker_num'] ?? 1;
        $avgRequestsPerWorker = $workerNum > 0 ? $totalRequests / $workerNum : 0;

        // Get previous stats for this worker
        $prevStats = $this->workerStats[$workerId] ?? null;
        
        $health = [
            'worker_id' => $workerId,
            'status' => 'healthy',
            'timestamp' => time(),
            'avg_requests_per_worker' => round($avgRequestsPerWorker, 2),
            'total_requests' => $totalRequests,
            'issues' => [],
        ];

        // Check for issues
        $memoryLimit = $this->config['memory_limit_mb'] ?? 512;
        $currentMemory = memory_get_usage(true) / 1024 / 1024;
        
        if ($currentMemory > $memoryLimit) {
            $health['issues'][] = "High memory usage: {$currentMemory}MB > {$memoryLimit}MB";
            $health['status'] = 'degraded';
        }

        // Check if worker might be stuck (no progress)
        if ($prevStats && isset($prevStats['timestamp'])) {
            $timeSinceLastCheck = time() - $prevStats['timestamp'];
            
            if ($timeSinceLastCheck > 60) {
                $health['issues'][] = "Worker might be stuck (no heartbeat for {$timeSinceLastCheck}s)";
                $health['status'] = 'unhealthy';
            }
        }

        // Check connection pool health if available
        if (class_exists(\Core\Database\Swoole\PoolMetrics::class)) {
            $poolHealth = $this->checkPoolHealth();
            if (!empty($poolHealth['issues'])) {
                $health['issues'] = array_merge($health['issues'], $poolHealth['issues']);
                if ($poolHealth['status'] === 'unhealthy') {
                    $health['status'] = 'unhealthy';
                } elseif ($poolHealth['status'] === 'degraded' && $health['status'] === 'healthy') {
                    $health['status'] = 'degraded';
                }
            }
        }

        return $health;
    }

    /**
     * Check health of connection pools.
     *
     * @return array
     */
    private function checkPoolHealth(): array
    {
        $issues = [];
        $worstStatus = 'healthy';

        $allMetrics = \Core\Database\Swoole\PoolMetrics::getAllMetrics();
        
        foreach ($allMetrics as $poolName => $metrics) {
            $status = \Core\Database\Swoole\PoolMetrics::getHealthStatus($poolName);
            
            if ($status === 'unhealthy') {
                $issues[] = "Pool '{$poolName}' is unhealthy (utilization: {$metrics['utilization']})";
                $worstStatus = 'unhealthy';
            } elseif ($status === 'degraded' && $worstStatus !== 'unhealthy') {
                $issues[] = "Pool '{$poolName}' is degraded (utilization: {$metrics['utilization']})";
                $worstStatus = 'degraded';
            }
        }

        return [
            'status' => $worstStatus,
            'issues' => $issues,
        ];
    }

    /**
     * Handle an unhealthy worker.
     *
     * @param int $workerId
     * @param array $health
     * @return void
     */
    private function handleUnhealthyWorker(int $workerId, array $health): void
    {
        $autoRestart = $this->config['auto_restart'] ?? false;
        
        if (!$autoRestart) {
            $this->logger->warning("Auto-restart disabled, worker #{$workerId} remains unhealthy");
            return;
        }

        $this->logger->info("Attempting to restart unhealthy worker #{$workerId}");
        
        try {
            // Request graceful restart of the worker
            $this->server->reload();
            
            $this->logger->info("Worker #{$workerId} restart initiated");
        } catch (\Throwable $e) {
            $this->logger->error("Failed to restart worker #{$workerId}", [
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get health status for all workers.
     *
     * @return array
     */
    public function getAllWorkerHealth(): array
    {
        return $this->workerStats;
    }

    /**
     * Get health status for a specific worker.
     *
     * @param int $workerId
     * @return array|null
     */
    public function getWorkerHealth(int $workerId): ?array
    {
        return $this->workerStats[$workerId] ?? null;
    }

    /**
     * Get overall health summary.
     *
     * @return array
     */
    public function getHealthSummary(): array
    {
        $healthy = 0;
        $degraded = 0;
        $unhealthy = 0;

        foreach ($this->workerStats as $stats) {
            match($stats['status']) {
                'healthy' => $healthy++,
                'degraded' => $degraded++,
                'unhealthy' => $unhealthy++,
                default => null,
            };
        }

        $total = count($this->workerStats);
        $overallStatus = 'healthy';

        if ($unhealthy > 0) {
            $overallStatus = 'unhealthy';
        } elseif ($degraded > $total / 2) {
            $overallStatus = 'degraded';
        }

        return [
            'overall_status' => $overallStatus,
            'total_workers' => $total,
            'healthy_workers' => $healthy,
            'degraded_workers' => $degraded,
            'unhealthy_workers' => $unhealthy,
            'last_check' => time(),
        ];
    }
}
