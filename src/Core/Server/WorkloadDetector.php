<?php

declare(strict_types=1);

namespace Core\Server;

use Psr\Log\LoggerInterface;

/**
 * Detects application workload type to optimize worker configuration.
 * 
 * Analyzes application behavior to determine if it's:
 * - CPU-bound: Heavy computation, minimal I/O
 * - I/O-bound: Database queries, API calls, file operations
 * - Mixed: Combination of both
 */
class WorkloadDetector
{
    private array $metrics = [];
    private int $sampleCount = 0;
    private const MAX_SAMPLES = 1000;

    public function __construct(
        private LoggerInterface $logger
    ) {}

    /**
     * Record metrics for a single request.
     *
     * @param array $requestMetrics
     * @return void
     */
    public function recordRequest(array $requestMetrics): void
    {
        $this->sampleCount++;

        foreach ($requestMetrics as $key => $value) {
            if (!isset($this->metrics[$key])) {
                $this->metrics[$key] = [];
            }
            
            $this->metrics[$key][] = $value;
            
            // Keep only last MAX_SAMPLES
            if (count($this->metrics[$key]) > self::MAX_SAMPLES) {
                array_shift($this->metrics[$key]);
            }
        }
    }

    /**
     * Detect the workload type based on collected metrics.
     *
     * @return string 'cpu_bound', 'io_bound', or 'mixed'
     */
    public function detectWorkloadType(): string
    {
        if ($this->sampleCount < 100) {
            // Not enough data yet, return default
            return 'mixed';
        }

        $ioRatio = $this->calculateIoRatio();
        $cpuRatio = $this->calculateCpuRatio();

        $this->logger->debug('Workload detection', [
            'io_ratio' => round($ioRatio, 3),
            'cpu_ratio' => round($cpuRatio, 3),
            'sample_count' => $this->sampleCount,
        ]);

        // I/O-bound: More than 70% time waiting on I/O
        if ($ioRatio > 0.7) {
            return 'io_bound';
        }

        // CPU-bound: More than 70% time on CPU
        if ($cpuRatio > 0.7) {
            return 'cpu_bound';
        }

        // Mixed workload
        return 'mixed';
    }

    /**
     * Calculate optimal worker number based on workload type.
     *
     * @return int
     */
    public function calculateOptimalWorkerNum(): int
    {
        $workloadType = $this->detectWorkloadType();
        $cpuNum = swoole_cpu_num();
        $isProduction = config('app.env', 'production') === 'production';

        $workerNum = match($workloadType) {
            'cpu_bound' => $cpuNum, // CPU-bound: 1 worker per core
            'io_bound' => $cpuNum * 4, // I/O-bound: 4x cores for concurrent I/O
            'mixed' => $cpuNum * 2, // Mixed: 2x cores
        };

        // In development, use fewer workers to save resources
        if (!$isProduction) {
            $workerNum = max(1, (int) ($workerNum / 2));
        }

        $this->logger->info('Calculated optimal worker num', [
            'workload_type' => $workloadType,
            'cpu_num' => $cpuNum,
            'optimal_workers' => $workerNum,
            'is_production' => $isProduction,
        ]);

        return $workerNum;
    }

    /**
     * Calculate I/O ratio from metrics.
     *
     * @return float
     */
    private function calculateIoRatio(): float
    {
        if (empty($this->metrics['io_time']) || empty($this->metrics['total_time'])) {
            // Estimate based on database/redis queries
            $hasDbQueries = !empty($this->metrics['db_queries']);
            $hasRedisOps = !empty($this->metrics['redis_ops']);
            $hasExternalCalls = !empty($this->metrics['http_calls']);

            if ($hasDbQueries || $hasRedisOps || $hasExternalCalls) {
                return 0.6; // Assume moderate I/O
            }

            return 0.3; // Assume low I/O
        }

        $totalIoTime = array_sum($this->metrics['io_time']);
        $totalTime = array_sum($this->metrics['total_time']);

        return $totalTime > 0 ? $totalIoTime / $totalTime : 0;
    }

    /**
     * Calculate CPU ratio from metrics.
     *
     * @return float
     */
    private function calculateCpuRatio(): float
    {
        if (empty($this->metrics['cpu_time']) || empty($this->metrics['total_time'])) {
            // Estimate: If low I/O, assume high CPU
            $ioRatio = $this->calculateIoRatio();
            return max(0, 1 - $ioRatio);
        }

        $totalCpuTime = array_sum($this->metrics['cpu_time']);
        $totalTime = array_sum($this->metrics['total_time']);

        return $totalTime > 0 ? $totalCpuTime / $totalTime : 0;
    }

    /**
     * Get current metrics summary.
     *
     * @return array
     */
    public function getMetrics(): array
    {
        if ($this->sampleCount === 0) {
            return [
                'workload_type' => 'unknown',
                'sample_count' => 0,
            ];
        }

        return [
            'workload_type' => $this->detectWorkloadType(),
            'io_ratio' => round($this->calculateIoRatio(), 3),
            'cpu_ratio' => round($this->calculateCpuRatio(), 3),
            'sample_count' => $this->sampleCount,
            'optimal_workers' => $this->calculateOptimalWorkerNum(),
            'avg_response_time' => $this->getAverageMetric('total_time'),
            'avg_db_queries' => $this->getAverageMetric('db_queries'),
            'avg_redis_ops' => $this->getAverageMetric('redis_ops'),
            'avg_http_calls' => $this->getAverageMetric('http_calls'),
        ];
    }

    /**
     * Get average value for a metric.
     *
     * @param string $key
     * @return float
     */
    private function getAverageMetric(string $key): float
    {
        if (empty($this->metrics[$key])) {
            return 0.0;
        }

        $sum = array_sum($this->metrics[$key]);
        $count = count($this->metrics[$key]);

        return $count > 0 ? $sum / $count : 0.0;
    }

    /**
     * Reset all metrics. Useful for testing or recalibration.
     *
     * @return void
     */
    public function reset(): void
    {
        $this->metrics = [];
        $this->sampleCount = 0;
        
        $this->logger->debug('Workload detector metrics reset');
    }
}
