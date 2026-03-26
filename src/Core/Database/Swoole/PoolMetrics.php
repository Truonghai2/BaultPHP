<?php

declare(strict_types=1);

namespace Core\Database\Swoole;

/**
 * Collects and tracks metrics for Swoole connection pools.
 * 
 * Provides insights into pool usage, performance, and health.
 */
class PoolMetrics
{
    /**
     * Metrics storage per pool.
     * 
     * @var array<string, array>
     */
    private static array $metrics = [];

    /**
     * Initialize metrics for a pool.
     *
     * @param string $poolName
     * @param int $poolSize
     * @return void
     */
    public static function initialize(string $poolName, int $poolSize): void
    {
        self::$metrics[$poolName] = [
            'pool_size' => $poolSize,
            'total_connections' => 0,
            'active_connections' => 0,
            'idle_connections' => $poolSize,
            'wait_queue_length' => 0,
            'total_acquires' => 0,
            'total_releases' => 0,
            'total_wait_time_ms' => 0.0,
            'max_wait_time_ms' => 0.0,
            'exhaustion_count' => 0,
            'error_count' => 0,
            'circuit_breaker_trips' => 0,
            'last_exhaustion_time' => null,
            'created_at' => time(),
        ];
    }

    /**
     * Record a connection acquire event.
     *
     * @param string $poolName
     * @param float $waitTimeMs
     * @return void
     */
    public static function recordConnectionAcquire(string $poolName, float $waitTimeMs = 0.0): void
    {
        if (!isset(self::$metrics[$poolName])) {
            return;
        }

        self::$metrics[$poolName]['total_acquires']++;
        self::$metrics[$poolName]['active_connections']++;
        self::$metrics[$poolName]['idle_connections']--;
        self::$metrics[$poolName]['total_wait_time_ms'] += $waitTimeMs;
        
        if ($waitTimeMs > self::$metrics[$poolName]['max_wait_time_ms']) {
            self::$metrics[$poolName]['max_wait_time_ms'] = $waitTimeMs;
        }
    }

    /**
     * Record a connection release event.
     *
     * @param string $poolName
     * @return void
     */
    public static function recordConnectionRelease(string $poolName): void
    {
        if (!isset(self::$metrics[$poolName])) {
            return;
        }

        self::$metrics[$poolName]['total_releases']++;
        self::$metrics[$poolName]['active_connections']--;
        self::$metrics[$poolName]['idle_connections']++;
    }

    /**
     * Record a pool exhaustion event.
     *
     * @param string $poolName
     * @return void
     */
    public static function recordPoolExhaustion(string $poolName): void
    {
        if (!isset(self::$metrics[$poolName])) {
            return;
        }

        self::$metrics[$poolName]['exhaustion_count']++;
        self::$metrics[$poolName]['last_exhaustion_time'] = time();
    }

    /**
     * Record a connection error.
     *
     * @param string $poolName
     * @return void
     */
    public static function recordError(string $poolName): void
    {
        if (!isset(self::$metrics[$poolName])) {
            return;
        }

        self::$metrics[$poolName]['error_count']++;
    }

    /**
     * Record a circuit breaker trip.
     *
     * @param string $poolName
     * @return void
     */
    public static function recordCircuitBreakerTrip(string $poolName): void
    {
        if (!isset(self::$metrics[$poolName])) {
            return;
        }

        self::$metrics[$poolName]['circuit_breaker_trips']++;
    }

    /**
     * Update wait queue length.
     *
     * @param string $poolName
     * @param int $length
     * @return void
     */
    public static function updateWaitQueueLength(string $poolName, int $length): void
    {
        if (!isset(self::$metrics[$poolName])) {
            return;
        }

        self::$metrics[$poolName]['wait_queue_length'] = $length;
    }

    /**
     * Get metrics for a specific pool.
     *
     * @param string $poolName
     * @return array
     */
    public static function getMetrics(string $poolName): array
    {
        if (!isset(self::$metrics[$poolName])) {
            return [];
        }

        $metrics = self::$metrics[$poolName];
        
        // Calculate derived metrics
        $totalAcquires = $metrics['total_acquires'];
        $avgWaitTime = $totalAcquires > 0 
            ? $metrics['total_wait_time_ms'] / $totalAcquires 
            : 0.0;

        $utilization = $metrics['pool_size'] > 0 
            ? $metrics['active_connections'] / $metrics['pool_size'] 
            : 0.0;

        return array_merge($metrics, [
            'avg_wait_time_ms' => round($avgWaitTime, 2),
            'utilization' => round($utilization, 4),
            'is_saturated' => $utilization > 0.9,
            'is_underutilized' => $utilization < 0.3,
            'uptime_seconds' => time() - $metrics['created_at'],
        ]);
    }

    /**
     * Get metrics for all pools.
     *
     * @return array<string, array>
     */
    public static function getAllMetrics(): array
    {
        $result = [];
        
        foreach (array_keys(self::$metrics) as $poolName) {
            $result[$poolName] = self::getMetrics($poolName);
        }
        
        return $result;
    }

    /**
     * Get health status for a pool.
     *
     * @param string $poolName
     * @return string 'healthy', 'degraded', or 'unhealthy'
     */
    public static function getHealthStatus(string $poolName): string
    {
        $metrics = self::getMetrics($poolName);
        
        if (empty($metrics)) {
            return 'unknown';
        }

        // Unhealthy conditions
        if ($metrics['utilization'] > 0.95) {
            return 'unhealthy'; // Pool is saturated
        }

        if ($metrics['exhaustion_count'] > 10) {
            return 'unhealthy'; // Frequent exhaustions
        }

        if ($metrics['circuit_breaker_trips'] > 0) {
            return 'unhealthy'; // Circuit breaker tripped
        }

        // Degraded conditions
        if ($metrics['utilization'] > 0.85) {
            return 'degraded'; // High utilization
        }

        if ($metrics['avg_wait_time_ms'] > 100) {
            return 'degraded'; // High wait times
        }

        if ($metrics['error_count'] > 5) {
            return 'degraded'; // Some errors
        }

        return 'healthy';
    }

    /**
     * Reset metrics for a pool.
     *
     * @param string $poolName
     * @return void
     */
    public static function reset(string $poolName): void
    {
        if (isset(self::$metrics[$poolName])) {
            $poolSize = self::$metrics[$poolName]['pool_size'];
            self::initialize($poolName, $poolSize);
        }
    }

    /**
     * Reset all metrics.
     *
     * @return void
     */
    public static function resetAll(): void
    {
        self::$metrics = [];
    }
}
