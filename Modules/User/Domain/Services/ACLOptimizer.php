<?php

namespace Modules\User\Domain\Services;

use Core\Cache\CacheManager;
use Core\Support\Facades\Audit;
use Modules\User\Infrastructure\Models\User;

/**
 * ACLOptimizer
 * 
 * Advanced optimization utilities for ACL system.
 * Provides multi-level caching, batch operations, and performance monitoring.
 */
class ACLOptimizer
{
    private const L1_TTL = 60; // APCu cache: 1 minute
    private const L2_TTL = 3600; // Redis cache: 1 hour
    private const METRICS_KEY = 'acl:metrics';

    public function __construct(
        private CacheManager $cache,
        private AccessControlService $acl
    ) {}

    /**
     * Get permission with multi-level caching (L1: APCu, L2: Redis).
     * 
     * @param User $user
     * @return array
     */
    public function getUserPermissions(User $user): array
    {
        $userId = $user->id;

        // L1: Check APCu (fastest - shared memory)
        if (function_exists('apcu_fetch')) {
            $l1Key = "acl:l1:{$userId}";
            $l1Data = apcu_fetch($l1Key, $success);
            
            if ($success) {
                $this->recordMetric('l1_hit');
                return $l1Data;
            }
        }

        // L2: Check Redis (fast - network)
        $l2Key = "acl:all_perms:{$userId}";
        $l2Data = $this->cache->store()->get($l2Key);

        if ($l2Data) {
            $permissions = json_decode($l2Data, true);
            
            // Store in L1 for next time
            if (function_exists('apcu_store')) {
                apcu_store($l1Key, $permissions, self::L1_TTL);
            }
            
            $this->recordMetric('l2_hit');
            return $permissions;
        }

        // L3: Load from database (slowest)
        $this->recordMetric('cache_miss');
        
        // This will load and cache in Redis
        // (handled by AccessControlService)
        return [];
    }

    /**
     * Warm cache for multiple users (deployment, popular users).
     * 
     * @param array $userIds
     * @return array Stats
     */
    public function warmCache(array $userIds): array
    {
        $startTime = microtime(true);
        $warmed = 0;
        $failed = 0;

        foreach ($userIds as $userId) {
            try {
                $user = User::find($userId);
                if ($user) {
                    // Force reload permissions
                    $this->acl->flushCacheForUser($userId);
                    
                    // Trigger load (will cache)
                    $user->can('dummy:permission'); // This triggers loadAndCacheUserPermissions
                    
                    $warmed++;
                }
            } catch (\Exception $e) {
                $failed++;
            }
        }

        $duration = microtime(true) - $startTime;

        $stats = [
            'total' => count($userIds),
            'warmed' => $warmed,
            'failed' => $failed,
            'duration' => round($duration, 2) . 's',
            'avg_time' => round($duration / max(count($userIds), 1) * 1000, 2) . 'ms'
        ];

        // Audit log
        Audit::log(
            'system',
            "ACL cache warmed for {$warmed} users",
            $stats,
            'info'
        );

        return $stats;
    }

    /**
     * Batch permission check (optimized).
     * 
     * @param User $user
     * @param array $permissions ['permission1', 'permission2', ...]
     * @param mixed $context
     * @return array ['permission1' => true, 'permission2' => false, ...]
     */
    public function checkBatch(User $user, array $permissions, $context = null): array
    {
        $startTime = microtime(true);
        $results = [];

        // Single permissions load for all checks
        foreach ($permissions as $permission) {
            $results[$permission] = $user->can($permission, $context);
        }

        $duration = microtime(true) - $startTime;
        
        // Record metrics
        $this->recordMetric('batch_check', [
            'count' => count($permissions),
            'duration_ms' => round($duration * 1000, 2)
        ]);

        return $results;
    }

    /**
     * Invalidate cache at all levels for a user.
     * 
     * @param int $userId
     */
    public function invalidateAllLevels(int $userId): void
    {
        // L1: APCu
        if (function_exists('apcu_delete')) {
            apcu_delete("acl:l1:{$userId}");
        }

        // L2: Redis
        $this->cache->store()->delete("acl:all_perms:{$userId}");

        // Record metric
        $this->recordMetric('cache_invalidate');
    }

    /**
     * Batch invalidate for multiple users.
     * 
     * @param array $userIds
     */
    public function invalidateBatch(array $userIds): void
    {
        foreach ($userIds as $userId) {
            $this->invalidateAllLevels($userId);
        }

        $this->recordMetric('batch_invalidate', ['count' => count($userIds)]);
    }

    /**
     * Get ACL performance metrics.
     * 
     * @return array
     */
    public function getMetrics(): array
    {
        $metrics = $this->cache->store()->get(self::METRICS_KEY);
        return $metrics ? json_decode($metrics, true) : [
            'l1_hits' => 0,
            'l2_hits' => 0,
            'cache_misses' => 0,
            'batch_checks' => 0,
            'cache_invalidations' => 0,
            'total_checks' => 0
        ];
    }

    /**
     * Reset metrics.
     */
    public function resetMetrics(): void
    {
        $this->cache->store()->delete(self::METRICS_KEY);
    }

    /**
     * Record a metric.
     * 
     * @param string $type
     * @param array $data
     */
    private function recordMetric(string $type, array $data = []): void
    {
        $metrics = $this->getMetrics();

        switch ($type) {
            case 'l1_hit':
                $metrics['l1_hits']++;
                break;
            case 'l2_hit':
                $metrics['l2_hits']++;
                break;
            case 'cache_miss':
                $metrics['cache_misses']++;
                break;
            case 'batch_check':
                $metrics['batch_checks']++;
                if (isset($data['count'])) {
                    $metrics['total_checks'] += $data['count'];
                }
                break;
            case 'batch_invalidate':
            case 'cache_invalidate':
                $metrics['cache_invalidations']++;
                break;
        }

        // Store metrics (with TTL of 1 day)
        $this->cache->store()->set(self::METRICS_KEY, json_encode($metrics), 86400);
    }

    /**
     * Get cache hit rate percentage.
     * 
     * @return float
     */
    public function getCacheHitRate(): float
    {
        $metrics = $this->getMetrics();
        $hits = $metrics['l1_hits'] + $metrics['l2_hits'];
        $total = $hits + $metrics['cache_misses'];

        return $total > 0 ? round(($hits / $total) * 100, 2) : 0.0;
    }

    /**
     * Get performance report.
     * 
     * @return array
     */
    public function getPerformanceReport(): array
    {
        $metrics = $this->getMetrics();
        $hitRate = $this->getCacheHitRate();

        return [
            'cache_performance' => [
                'l1_hits' => $metrics['l1_hits'],
                'l2_hits' => $metrics['l2_hits'],
                'cache_misses' => $metrics['cache_misses'],
                'hit_rate' => $hitRate . '%'
            ],
            'operations' => [
                'total_checks' => $metrics['total_checks'],
                'batch_checks' => $metrics['batch_checks'],
                'cache_invalidations' => $metrics['cache_invalidations']
            ],
            'health' => [
                'status' => $hitRate >= 95 ? 'excellent' : ($hitRate >= 85 ? 'good' : 'needs_improvement'),
                'recommendation' => $this->getRecommendation($hitRate, $metrics)
            ]
        ];
    }

    /**
     * Get optimization recommendations based on metrics.
     * 
     * @param float $hitRate
     * @param array $metrics
     * @return string
     */
    private function getRecommendation(float $hitRate, array $metrics): string
    {
        if ($hitRate < 85) {
            return 'Consider increasing cache TTL or warming cache for popular users';
        }

        if ($metrics['cache_misses'] > 1000) {
            return 'High cache miss rate detected. Run cache warming for active users';
        }

        if ($metrics['l1_hits'] < $metrics['l2_hits'] * 0.3) {
            return 'L1 cache underutilized. Consider increasing L1 TTL';
        }

        return 'ACL performance is optimal';
    }
}

