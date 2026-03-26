<?php

declare(strict_types=1);

namespace Core\Cache;

use Core\Contracts\Cache\Store;
use Core\Support\Facades\Log;
use Psr\SimpleCache\CacheInterface;

/**
 * Multi-Tier Cache Manager
 *
 * Manages multiple cache layers (L1, L2, L3) with automatic promotion/demotion.
 * Supports AI predictive caching and CRDT for distributed scenarios.
 *
 * Cache Tiers:
 * - L1: APCu/In-memory (fastest, limited size)
 * - L2: Redis (fast, network)
 * - L3: Database/File (slowest, persistent)
 *
 * Features:
 * - Automatic tier promotion/demotion
 * - AI predictive preloading
 * - CRDT for distributed scenarios
 * - Edge cache integration
 */
class MultiTierCacheManager implements CacheInterface
{
    protected ?CacheInterface $l1Cache = null; // APCu/In-memory
    protected CacheInterface $l2Cache; // Redis
    protected ?CacheInterface $l3Cache = null; // Database/File
    protected ?AIPredictiveCache $predictiveCache = null;
    protected ?CrdtCache $crdtCache = null;

    protected array $config = [];
    protected array $stats = [
        'l1_hits' => 0,
        'l2_hits' => 0,
        'l3_hits' => 0,
        'misses' => 0,
        'promotions' => 0,
        'demotions' => 0,
    ];

    public function __construct(
        CacheInterface $l2Cache,
        ?CacheInterface $l1Cache = null,
        ?CacheInterface $l3Cache = null,
        array $config = [],
    ) {
        $this->l2Cache = $l2Cache;
        $this->l1Cache = $l1Cache;
        $this->l3Cache = $l3Cache;
        $this->config = $config;

        // Initialize predictive cache if enabled
        if ($config['predictive']['enabled'] ?? false) {
            $store = null;
            if ($l2Cache instanceof Repository) {
                // Access protected store via reflection or use cache directly
                $store = $l2Cache;
            }
            $this->predictiveCache = new AIPredictiveCache(
                $l2Cache,
                $store,
                $config['predictive'] ?? []
            );
        }

        // Initialize CRDT cache if enabled
        if ($config['crdt']['enabled'] ?? false) {
            $store = null;
            if ($l2Cache instanceof Repository) {
                $store = $l2Cache;
            }
            $this->crdtCache = new CrdtCache(
                $l2Cache,
                $store,
                $config['crdt'] ?? []
            );
        }
    }

    /**
     * Get value from cache (checks all tiers)
     */
    public function get(string $key, mixed $default = null): mixed
    {
        // L1: Check in-memory cache (fastest)
        if ($this->l1Cache !== null) {
            $value = $this->l1Cache->get($key);
            if ($value !== null) {
                $this->stats['l1_hits']++;
                Log::debug("Cache L1 hit", ['key' => $key]);
                return $value;
            }
        }

        // L2: Check Redis cache
        $l2Value = $this->l2Cache->get($key);
        if ($l2Value !== null) {
            $this->stats['l2_hits']++;
            
            // Promote to L1
            if ($this->l1Cache !== null) {
                $this->promoteToL1($key, $l2Value);
            }
            
            Log::debug("Cache L2 hit", ['key' => $key]);
            return $l2Value;
        }

        // L3: Check persistent cache (database/file)
        if ($this->l3Cache !== null) {
            $l3Value = $this->l3Cache->get($key);
            if ($l3Value !== null) {
                $this->stats['l3_hits']++;
                
                // Promote to L2 and L1
                $this->promoteToL2($key, $l3Value);
                if ($this->l1Cache !== null) {
                    $this->promoteToL1($key, $l3Value);
                }
                
                Log::debug("Cache L3 hit", ['key' => $key]);
                return $l3Value;
            }
        }

        // Cache miss
        $this->stats['misses']++;
        
        // Use predictive cache if enabled
        if ($this->predictiveCache !== null) {
            return $this->predictiveCache->get($key, $default);
        }

        // Use CRDT cache if enabled
        if ($this->crdtCache !== null) {
            return $this->crdtCache->get($key, $default);
        }

        Log::debug("Cache miss", ['key' => $key]);
        return $default;
    }

    /**
     * Set value in cache (stores in all tiers)
     */
    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
    {
        $result = true;

        // Store in L1 (if available)
        if ($this->l1Cache !== null) {
            $l1Ttl = $this->calculateL1Ttl($ttl);
            $result = $this->l1Cache->set($key, $value, $l1Ttl) && $result;
        }

        // Store in L2
        $result = $this->l2Cache->set($key, $value, $ttl) && $result;

        // Store in L3 (if available and configured)
        if ($this->l3Cache !== null && ($this->config['l3']['persist_all'] ?? false)) {
            $result = $this->l3Cache->set($key, $value, $ttl) && $result;
        }

        // Update predictive cache
        if ($this->predictiveCache !== null) {
            $this->predictiveCache->set($key, $value, $ttl);
        }

        // Update CRDT cache
        if ($this->crdtCache !== null) {
            $this->crdtCache->set($key, $value, $ttl);
        }

        return $result;
    }

    /**
     * Delete a cache entry
     */
    public function delete(string $key): bool
    {
        $result = true;

        if ($this->l1Cache !== null) {
            $result = $this->l1Cache->delete($key) && $result;
        }

        $result = $this->l2Cache->delete($key) && $result;

        if ($this->l3Cache !== null) {
            $result = $this->l3Cache->delete($key) && $result;
        }

        if ($this->predictiveCache !== null) {
            $this->predictiveCache->delete($key);
        }

        if ($this->crdtCache !== null) {
            $this->crdtCache->delete($key);
        }

        return $result;
    }

    /**
     * Clear all cache
     */
    public function clear(): bool
    {
        $result = true;

        if ($this->l1Cache !== null) {
            $result = $this->l1Cache->clear() && $result;
        }

        $result = $this->l2Cache->clear() && $result;

        if ($this->l3Cache !== null) {
            $result = $this->l3Cache->clear() && $result;
        }

        if ($this->predictiveCache !== null) {
            $this->predictiveCache->clear();
        }

        if ($this->crdtCache !== null) {
            $this->crdtCache->clear();
        }

        return $result;
    }

    /**
     * Get multiple values
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $default);
        }
        return $results;
    }

    /**
     * Set multiple values
     */
    public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }
        return true;
    }

    /**
     * Delete multiple keys
     */
    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
        return true;
    }

    /**
     * Check if key exists
     */
    public function has(string $key): bool
    {
        if ($this->l1Cache !== null && $this->l1Cache->has($key)) {
            return true;
        }

        if ($this->l2Cache->has($key)) {
            return true;
        }

        if ($this->l3Cache !== null && $this->l3Cache->has($key)) {
            return true;
        }

        return false;
    }

    /**
     * Promote value to L1 cache
     */
    protected function promoteToL1(string $key, mixed $value): void
    {
        if ($this->l1Cache === null) {
            return;
        }

        $l1Ttl = $this->calculateL1Ttl(null);
        $this->l1Cache->set($key, $value, $l1Ttl);
        $this->stats['promotions']++;
    }

    /**
     * Promote value to L2 cache
     */
    protected function promoteToL2(string $key, mixed $value): void
    {
        $ttl = $this->config['l2']['default_ttl'] ?? 3600;
        $this->l2Cache->set($key, $value, $ttl);
        $this->stats['promotions']++;
    }

    /**
     * Calculate L1 TTL (usually shorter than L2)
     */
    protected function calculateL1Ttl(\DateInterval|int|null $ttl): int
    {
        if ($ttl === null) {
            return $this->config['l1']['default_ttl'] ?? 60; // 1 minute default
        }

        if (is_int($ttl)) {
            // L1 TTL is usually shorter (e.g., 10% of L2 TTL)
            $l1Ratio = $this->config['l1']['ttl_ratio'] ?? 0.1;
            return max(1, (int) ($ttl * $l1Ratio));
        }

        // For DateInterval, convert to seconds and apply ratio
        $seconds = $this->dateIntervalToSeconds($ttl);
        $l1Ratio = $this->config['l1']['ttl_ratio'] ?? 0.1;
        return max(1, (int) ($seconds * $l1Ratio));
    }

    /**
     * Convert DateInterval to seconds
     */
    protected function dateIntervalToSeconds(\DateInterval $interval): int
    {
        $reference = new \DateTimeImmutable();
        $endTime = $reference->add($interval);
        return $endTime->getTimestamp() - $reference->getTimestamp();
    }

    /**
     * Warm cache using predictive analysis
     */
    public function warmCache(callable $dataLoader, array $keys = []): void
    {
        if ($this->predictiveCache !== null) {
            $this->predictiveCache->warmCache($dataLoader, $keys);
        } else {
            // Manual warm-up
            foreach ($keys as $key) {
                if (!$this->has($key)) {
                    try {
                        $value = $dataLoader($key);
                        if ($value !== null) {
                            $this->set($key, $value);
                        }
                    } catch (\Throwable $e) {
                        Log::warning("Failed to warm cache", [
                            'key' => $key,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Get cache statistics
     */
    public function getStats(): array
    {
        $totalRequests = array_sum([
            $this->stats['l1_hits'],
            $this->stats['l2_hits'],
            $this->stats['l3_hits'],
            $this->stats['misses'],
        ]);

        $hitRate = $totalRequests > 0
            ? (($this->stats['l1_hits'] + $this->stats['l2_hits'] + $this->stats['l3_hits']) / $totalRequests) * 100
            : 0;

        return [
            'stats' => $this->stats,
            'hit_rate' => round($hitRate, 2),
            'total_requests' => $totalRequests,
            'predictive_enabled' => $this->predictiveCache !== null,
            'crdt_enabled' => $this->crdtCache !== null,
            'predictive_stats' => $this->predictiveCache?->getStats(),
            'crdt_stats' => $this->crdtCache?->getStats(),
        ];
    }

    /**
     * Reset statistics
     */
    public function resetStats(): void
    {
        $this->stats = [
            'l1_hits' => 0,
            'l2_hits' => 0,
            'l3_hits' => 0,
            'misses' => 0,
            'promotions' => 0,
            'demotions' => 0,
        ];
    }
}
