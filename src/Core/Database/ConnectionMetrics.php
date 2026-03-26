<?php

declare(strict_types=1);

namespace Core\Database;

use Core\Metrics\MetricsCollector;

/**
 * Collects and tracks database connection metrics for monitoring and optimization.
 */
class ConnectionMetrics
{
    private static array $queryExecutionTimes = [];
    private static array $connectionAcquisitionTimes = [];
    private static array $slowQueries = [];
    private static int $totalQueries = 0;
    private static int $failedQueries = 0;
    private static int $slowQueryThreshold = 1000; // ms

    public function __construct(
        private readonly ?MetricsCollector $metrics = null
    ) {
    }

    /**
     * Record a query execution.
     */
    public function recordQuery(
        string $sql,
        float $durationMs,
        bool $success,
        string $connection = 'default'
    ): void {
        self::$totalQueries++;

        if (!$success) {
            self::$failedQueries++;
        }

        // Track query execution time
        self::$queryExecutionTimes[] = [
            'sql' => $sql,
            'duration_ms' => $durationMs,
            'success' => $success,
            'connection' => $connection,
            'timestamp' => time(),
        ];

        // Track slow queries
        if ($durationMs > self::$slowQueryThreshold) {
            self::$slowQueries[] = [
                'sql' => $this->sanitizeSql($sql),
                'duration_ms' => $durationMs,
                'connection' => $connection,
                'timestamp' => time(),
            ];
        }

        // Send to metrics collector if available
        if ($this->metrics) {
            $this->metrics->histogram('db_query_duration_seconds', $durationMs / 1000, [
                'connection' => $connection,
                'success' => $success ? 'true' : 'false',
            ]);

            if (!$success) {
                $this->metrics->increment('db_query_errors_total', [
                    'connection' => $connection,
                ]);
            }
        }

        // Keep only last 1000 queries in memory
        if (count(self::$queryExecutionTimes) > 1000) {
            array_shift(self::$queryExecutionTimes);
        }
    }

    /**
     * Record connection acquisition time.
     */
    public function recordConnectionAcquisition(
        string $connection,
        float $waitTimeMs
    ): void {
        self::$connectionAcquisitionTimes[] = [
            'connection' => $connection,
            'wait_time_ms' => $waitTimeMs,
            'timestamp' => time(),
        ];

        if ($this->metrics) {
            $this->metrics->histogram('db_connection_acquisition_seconds', $waitTimeMs / 1000, [
                'connection' => $connection,
            ]);
        }

        // Keep only last 1000 acquisitions
        if (count(self::$connectionAcquisitionTimes) > 1000) {
            array_shift(self::$connectionAcquisitionTimes);
        }
    }

    /**
     * Record connection pool statistics.
     */
    public function recordPoolStats(
        string $connection,
        int $poolSize,
        int $connectionsInUse,
        int $connectionsIdle
    ): void {
        if ($this->metrics) {
            $this->metrics->gauge('db_pool_size', $poolSize, [
                'connection' => $connection,
            ]);

            $this->metrics->gauge('db_pool_connections_in_use', $connectionsInUse, [
                'connection' => $connection,
            ]);

            $this->metrics->gauge('db_pool_connections_idle', $connectionsIdle, [
                'connection' => $connection,
            ]);

            $utilization = $poolSize > 0 ? ($connectionsInUse / $poolSize) * 100 : 0;
            $this->metrics->gauge('db_pool_utilization_percent', $utilization, [
                'connection' => $connection,
            ]);
        }
    }

    /**
     * Get query statistics.
     */
    public function getQueryStats(): array
    {
        $executionTimes = array_column(self::$queryExecutionTimes, 'duration_ms');
        sort($executionTimes);

        $count = count($executionTimes);

        return [
            'total_queries' => self::$totalQueries,
            'failed_queries' => self::$failedQueries,
            'success_rate' => self::$totalQueries > 0 
                ? (self::$totalQueries - self::$failedQueries) / self::$totalQueries * 100 
                : 100,
            'avg_duration_ms' => $count > 0 ? array_sum($executionTimes) / $count : 0,
            'p50_duration_ms' => $this->percentile($executionTimes, 0.50),
            'p95_duration_ms' => $this->percentile($executionTimes, 0.95),
            'p99_duration_ms' => $this->percentile($executionTimes, 0.99),
            'slow_queries_count' => count(self::$slowQueries),
            'queries_per_second' => $this->calculateQPS(),
        ];
    }

    /**
     * Get slow queries.
     */
    public function getSlowQueries(int $limit = 10): array
    {
        $queries = self::$slowQueries;
        usort($queries, fn($a, $b) => $b['duration_ms'] <=> $a['duration_ms']);
        return array_slice($queries, 0, $limit);
    }

    /**
     * Get connection acquisition stats.
     */
    public function getConnectionStats(): array
    {
        $waitTimes = array_column(self::$connectionAcquisitionTimes, 'wait_time_ms');
        sort($waitTimes);

        $count = count($waitTimes);

        return [
            'total_acquisitions' => $count,
            'avg_wait_time_ms' => $count > 0 ? array_sum($waitTimes) / $count : 0,
            'p95_wait_time_ms' => $this->percentile($waitTimes, 0.95),
            'p99_wait_time_ms' => $this->percentile($waitTimes, 0.99),
        ];
    }

    /**
     * Reset all metrics.
     */
    public function reset(): void
    {
        self::$queryExecutionTimes = [];
        self::$connectionAcquisitionTimes = [];
        self::$slowQueries = [];
        self::$totalQueries = 0;
        self::$failedQueries = 0;
    }

    /**
     * Set slow query threshold.
     */
    public function setSlowQueryThreshold(int $milliseconds): void
    {
        self::$slowQueryThreshold = $milliseconds;
    }

    /**
     * Calculate percentile value.
     */
    private function percentile(array $values, float $percentile): float
    {
        if (empty($values)) {
            return 0;
        }

        $index = (int) ceil(count($values) * $percentile) - 1;
        return $values[max(0, $index)] ?? 0;
    }

    /**
     * Calculate queries per second.
     */
    private function calculateQPS(): float
    {
        if (empty(self::$queryExecutionTimes)) {
            return 0;
        }

        $timestamps = array_column(self::$queryExecutionTimes, 'timestamp');
        $minTime = min($timestamps);
        $maxTime = max($timestamps);
        $duration = max(1, $maxTime - $minTime);

        return count(self::$queryExecutionTimes) / $duration;
    }

    /**
     * Sanitize SQL for logging (remove sensitive data).
     */
    private function sanitizeSql(string $sql): string
    {
        // Truncate long queries
        if (strlen($sql) > 500) {
            return substr($sql, 0, 500) . '...';
        }

        return $sql;
    }
}
