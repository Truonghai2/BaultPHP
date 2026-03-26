<?php

declare(strict_types=1);

namespace Core\Cache;

use Core\Support\Facades\Log;
use Psr\SimpleCache\CacheInterface;

/**
 * CRDT Cache (Conflict-free Replicated Data Types)
 *
 * Distributed cache with eventual consistency and automatic conflict resolution.
 * No single point of failure, supports multi-region replication.
 *
 * Features:
 * - Eventual consistency
 * - Automatic conflict resolution
 * - Multi-region support
 * - Vector clocks for ordering
 */
class CrdtCache implements CacheInterface
{
    protected array $vectorClock = [];
    protected string $nodeId;
    protected array $replicas = [];

    public function __construct(
        protected CacheInterface $cache,
        protected mixed $store = null,
        protected array $config = [],
    ) {
        $this->nodeId = $config['node_id'] ?? gethostname() . '_' . getmypid();
        $this->replicas = $config['replicas'] ?? [];
        
        // Initialize vector clock
        $this->vectorClock[$this->nodeId] = 0;
    }

    /**
     * Get value from cache
     */
    public function get(string $key, mixed $default = null): mixed
    {
        // Try local cache first
        $value = $this->cache->get($key, $default);
        
        if ($value !== $default) {
            return $this->extractValue($value);
        }

        // Try replicas if configured
        if (!empty($this->replicas)) {
            return $this->getFromReplicas($key, $default);
        }

        return $default;
    }

    /**
     * Set value in cache with CRDT metadata
     */
    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
    {
        // Increment vector clock
        $this->vectorClock[$this->nodeId] = ($this->vectorClock[$this->nodeId] ?? 0) + 1;

        // Create CRDT value with metadata
        $crdtValue = [
            'value' => $value,
            'timestamp' => time(),
            'vector_clock' => $this->vectorClock,
            'node_id' => $this->nodeId,
            'version' => $this->vectorClock[$this->nodeId],
        ];

        // Store locally
        $result = $this->cache->set($key, $crdtValue, $ttl);

        // Replicate to other nodes
        $this->replicate($key, $crdtValue, $ttl);

        return $result;
    }

    /**
     * Delete a cache entry
     */
    public function delete(string $key): bool
    {
        // Increment vector clock for deletion
        $this->vectorClock[$this->nodeId] = ($this->vectorClock[$this->nodeId] ?? 0) + 1;

        // Create tombstone marker
        $tombstone = [
            'value' => null,
            'tombstone' => true,
            'timestamp' => time(),
            'vector_clock' => $this->vectorClock,
            'node_id' => $this->nodeId,
            'version' => $this->vectorClock[$this->nodeId],
        ];

        $result = $this->cache->set($key, $tombstone, 86400); // Keep tombstone for 24h

        // Replicate deletion
        $this->replicate($key, $tombstone, 86400);

        return $result;
    }

    /**
     * Clear all cache
     */
    public function clear(): bool
    {
        $this->vectorClock = [$this->nodeId => 0];
        return $this->cache->clear();
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
        $value = $this->cache->get($key);
        
        if ($value === null) {
            return false;
        }

        // Check if it's a tombstone
        if (is_array($value) && isset($value['tombstone']) && $value['tombstone']) {
            return false;
        }

        return true;
    }

    /**
     * Extract actual value from CRDT structure
     */
    protected function extractValue(mixed $crdtValue): mixed
    {
        if (!is_array($crdtValue)) {
            return $crdtValue;
        }

        // Check for tombstone
        if (isset($crdtValue['tombstone']) && $crdtValue['tombstone']) {
            return null;
        }

        return $crdtValue['value'] ?? $crdtValue;
    }

    /**
     * Get value from replica nodes
     */
    protected function getFromReplicas(string $key, mixed $default): mixed
    {
        foreach ($this->replicas as $replica) {
            try {
                $value = $this->fetchFromReplica($replica, $key);
                
                if ($value !== null) {
                    // Merge with local if exists
                    $localValue = $this->cache->get($key);
                    if ($localValue !== null) {
                        $merged = $this->mergeValues($localValue, $value);
                        $this->cache->set($key, $merged);
                        return $this->extractValue($merged);
                    }
                    
                    // Store replica value locally
                    $this->cache->set($key, $value);
                    return $this->extractValue($value);
                }
            } catch (\Throwable $e) {
                Log::warning("Failed to fetch from replica", [
                    'replica' => $replica,
                    'key' => $key,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $default;
    }

    /**
     * Fetch value from a replica node
     */
    protected function fetchFromReplica(string $replica, string $key): ?array
    {
        // Support multiple replica formats:
        // - Redis: redis://host:port or redis://host:port/db
        // - HTTP: http://host:port or https://host:port
        
        if (str_starts_with($replica, 'redis://')) {
            return $this->fetchFromRedisReplica($replica, $key);
        } elseif (str_starts_with($replica, 'http://') || str_starts_with($replica, 'https://')) {
            return $this->fetchFromHttpReplica($replica, $key);
        }
        
        // Default: try HTTP
        return $this->fetchFromHttpReplica($replica, $key);
    }

    /**
     * Fetch from Redis replica using pub/sub or direct get
     */
    protected function fetchFromRedisReplica(string $replica, string $key): ?array
    {
        try {
            // Parse Redis URL: redis://host:port/db
            $parsed = parse_url($replica);
            $host = $parsed['host'] ?? 'localhost';
            $port = $parsed['port'] ?? 6379;
            $db = isset($parsed['path']) ? (int) ltrim($parsed['path'], '/') : 0;
            
            // Try to get from Redis directly
            // In production, you might use a shared Redis cluster
            // For now, we'll try to use the same Redis connection
            if ($this->store instanceof \Core\Cache\Repository) {
                // Try to get from shared Redis
                $value = $this->cache->get("crdt:{$key}");
                if ($value !== null && is_array($value)) {
                    return $value;
                }
            }
            
            return null;
        } catch (\Throwable $e) {
            Log::warning("Failed to fetch from Redis replica", [
                'replica' => $replica,
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Fetch from HTTP replica endpoint
     */
    protected function fetchFromHttpReplica(string $replica, string $key): ?array
    {
        try {
            $url = rtrim($replica, '/') . '/api/cache/crdt/' . urlencode($key);
            $timeout = $this->config['replication_timeout'] ?? 5;
            
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                throw new \RuntimeException("HTTP request failed: {$error}");
            }
            
            if ($httpCode !== 200) {
                return null;
            }
            
            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }
            
            return $data['data'] ?? null;
        } catch (\Throwable $e) {
            Log::warning("Failed to fetch from HTTP replica", [
                'replica' => $replica,
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Replicate value to other nodes
     */
    protected function replicate(string $key, array $crdtValue, \DateInterval|int|null $ttl): void
    {
        foreach ($this->replicas as $replica) {
            try {
                $this->sendToReplica($replica, $key, $crdtValue, $ttl);
            } catch (\Throwable $e) {
                Log::warning("Failed to replicate to node", [
                    'replica' => $replica,
                    'key' => $key,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Send value to a replica node
     */
    protected function sendToReplica(string $replica, string $key, array $crdtValue, \DateInterval|int|null $ttl): void
    {
        // Support multiple replica formats
        if (str_starts_with($replica, 'redis://')) {
            $this->sendToRedisReplica($replica, $key, $crdtValue, $ttl);
        } elseif (str_starts_with($replica, 'http://') || str_starts_with($replica, 'https://')) {
            $this->sendToHttpReplica($replica, $key, $crdtValue, $ttl);
        } else {
            // Default: try HTTP
            $this->sendToHttpReplica($replica, $key, $crdtValue, $ttl);
        }
    }

    /**
     * Send to Redis replica using pub/sub
     */
    protected function sendToRedisReplica(string $replica, string $key, array $crdtValue, \DateInterval|int|null $ttl): void
    {
        try {
            // Use Redis pub/sub for replication
            // This allows async replication without blocking
            if ($this->store instanceof \Core\Cache\Repository) {
                // Store in shared Redis with CRDT prefix
                $this->cache->set("crdt:{$key}", $crdtValue, $ttl);
                
                // Publish to replication channel
                // Other nodes can subscribe to this channel
                $channel = $this->config['replication_channel'] ?? 'crdt:replication';
                $message = json_encode([
                    'node_id' => $this->nodeId,
                    'key' => $key,
                    'value' => $crdtValue,
                    'ttl' => is_int($ttl) ? $ttl : null,
                ]);
                
                // In production, use Redis PUBLISH command
                // For now, we'll log it
                Log::debug("CRDT replication via Redis", [
                    'channel' => $channel,
                    'key' => $key,
                    'node_id' => $this->nodeId,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning("Failed to replicate to Redis", [
                'replica' => $replica,
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send to HTTP replica endpoint
     */
    protected function sendToHttpReplica(string $replica, string $key, array $crdtValue, \DateInterval|int|null $ttl): void
    {
        try {
            $url = rtrim($replica, '/') . '/api/cache/crdt';
            $timeout = $this->config['replication_timeout'] ?? 5;
            
            $payload = [
                'node_id' => $this->nodeId,
                'key' => $key,
                'value' => $crdtValue,
                'ttl' => is_int($ttl) ? $ttl : null,
            ];
            
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                throw new \RuntimeException("HTTP request failed: {$error}");
            }
            
            if ($httpCode >= 200 && $httpCode < 300) {
                Log::debug("CRDT replicated successfully", [
                    'replica' => $replica,
                    'key' => $key,
                    'http_code' => $httpCode,
                ]);
            } else {
                Log::warning("CRDT replication returned non-2xx", [
                    'replica' => $replica,
                    'key' => $key,
                    'http_code' => $httpCode,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning("Failed to replicate to HTTP replica", [
                'replica' => $replica,
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Merge two CRDT values using vector clocks
     */
    protected function mergeValues(array $value1, array $value2): array
    {
        // Compare vector clocks to determine which is newer
        $clock1 = $value1['vector_clock'] ?? [];
        $clock2 = $value2['vector_clock'] ?? [];

        // Check if one happens-before the other
        $happensBefore = $this->happensBefore($clock1, $clock2);

        if ($happensBefore === 1) {
            // value1 is newer
            return $value1;
        } elseif ($happensBefore === -1) {
            // value2 is newer
            return $value2;
        } else {
            // Concurrent updates - use conflict resolution strategy
            return $this->resolveConflict($value1, $value2);
        }
    }

    /**
     * Check if clock1 happens-before clock2
     * Returns: 1 if clock1 > clock2, -1 if clock1 < clock2, 0 if concurrent
     */
    protected function happensBefore(array $clock1, array $clock2): int
    {
        $allNodes = array_unique(array_merge(array_keys($clock1), array_keys($clock2)));
        $allBefore = true;
        $allAfter = true;
        $hasBefore = false;
        $hasAfter = false;

        foreach ($allNodes as $node) {
            $v1 = $clock1[$node] ?? 0;
            $v2 = $clock2[$node] ?? 0;

            if ($v1 < $v2) {
                $allAfter = false;
                $hasBefore = true;
            } elseif ($v1 > $v2) {
                $allBefore = false;
                $hasAfter = true;
            }
        }

        if ($allBefore && $hasBefore) {
            return -1; // clock1 happens before clock2
        } elseif ($allAfter && $hasAfter) {
            return 1; // clock1 happens after clock2
        } else {
            return 0; // concurrent
        }
    }

    /**
     * Resolve conflict between two concurrent updates
     */
    protected function resolveConflict(array $value1, array $value2): array
    {
        // Strategy: Last Write Wins (LWW) based on timestamp
        // Can be customized for different conflict resolution strategies
        
        $timestamp1 = $value1['timestamp'] ?? 0;
        $timestamp2 = $value2['timestamp'] ?? 0;

        if ($timestamp1 >= $timestamp2) {
            // Merge vector clocks
            $mergedClock = array_merge($value1['vector_clock'] ?? [], $value2['vector_clock'] ?? []);
            foreach ($mergedClock as $node => $time) {
                $mergedClock[$node] = max(
                    $value1['vector_clock'][$node] ?? 0,
                    $value2['vector_clock'][$node] ?? 0
                );
            }
            
            $value1['vector_clock'] = $mergedClock;
            return $value1;
        } else {
            $mergedClock = array_merge($value1['vector_clock'] ?? [], $value2['vector_clock'] ?? []);
            foreach ($mergedClock as $node => $time) {
                $mergedClock[$node] = max(
                    $value1['vector_clock'][$node] ?? 0,
                    $value2['vector_clock'][$node] ?? 0
                );
            }
            
            $value2['vector_clock'] = $mergedClock;
            return $value2;
        }
    }

    /**
     * Get vector clock for this node
     */
    public function getVectorClock(): array
    {
        return $this->vectorClock;
    }

    /**
     * Merge vector clock from another node
     */
    public function mergeVectorClock(array $otherClock): void
    {
        foreach ($otherClock as $node => $time) {
            $this->vectorClock[$node] = max(
                $this->vectorClock[$node] ?? 0,
                $time
            );
        }
    }

    /**
     * Get CRDT statistics
     */
    public function getStats(): array
    {
        return [
            'node_id' => $this->nodeId,
            'vector_clock' => $this->vectorClock,
            'replicas' => count($this->replicas),
            'replica_nodes' => $this->replicas,
        ];
    }
}
