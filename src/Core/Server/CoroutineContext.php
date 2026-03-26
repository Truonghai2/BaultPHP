<?php

declare(strict_types=1);

namespace Core\Server;

use Swoole\Coroutine;

/**
 * Manages coroutine-specific context storage.
 * 
 * This class provides a safe way to store and retrieve data that is scoped
 * to a specific coroutine, similar to thread-local storage in multi-threaded environments.
 * 
 * Use cases:
 * - Storing request ID per coroutine
 * - Tracking parent-child coroutine relationships
 * - Storing user authentication data
 * - Coroutine-specific caching
 */
class CoroutineContext
{
    /**
     * Storage for all coroutine contexts.
     * Format: [coroutine_id => [key => value]]
     * 
     * @var array<int, array<string, mixed>>
     */
    private static array $contexts = [];

    /**
     * Track parent-child coroutine relationships.
     * Format: [child_cid => parent_cid]
     * 
     * @var array<int, int>
     */
    private static array $parentMap = [];

    /**
     * Set a value in the current coroutine's context.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public static function set(string $key, mixed $value): void
    {
        $cid = Coroutine::getCid();
        
        if ($cid < 0) {
            // Not in a coroutine context, skip
            return;
        }

        self::$contexts[$cid][$key] = $value;
    }

    /**
     * Get a value from the current coroutine's context.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $cid = Coroutine::getCid();
        
        if ($cid < 0) {
            // Not in a coroutine context
            return $default;
        }

        return self::$contexts[$cid][$key] ?? $default;
    }

    /**
     * Check if a key exists in the current coroutine's context.
     *
     * @param string $key
     * @return bool
     */
    public static function has(string $key): bool
    {
        $cid = Coroutine::getCid();
        
        if ($cid < 0) {
            return false;
        }

        return isset(self::$contexts[$cid][$key]);
    }

    /**
     * Remove a key from the current coroutine's context.
     *
     * @param string $key
     * @return void
     */
    public static function delete(string $key): void
    {
        $cid = Coroutine::getCid();
        
        if ($cid >= 0) {
            unset(self::$contexts[$cid][$key]);
        }
    }

    /**
     * Get all context data for the current coroutine.
     *
     * @return array
     */
    public static function all(): array
    {
        $cid = Coroutine::getCid();
        
        if ($cid < 0) {
            return [];
        }

        return self::$contexts[$cid] ?? [];
    }

    /**
     * Clear the entire context for the current coroutine.
     * This should be called when a coroutine finishes to prevent memory leaks.
     *
     * @return void
     */
    public static function clear(): void
    {
        $cid = Coroutine::getCid();
        
        if ($cid >= 0) {
            unset(self::$contexts[$cid]);
            unset(self::$parentMap[$cid]);
        }
    }

    /**
     * Copy context from one coroutine to another.
     * Useful when spawning child coroutines that need access to parent context.
     *
     * @param int $fromCid Source coroutine ID
     * @param int $toCid Target coroutine ID
     * @return void
     */
    public static function copy(int $fromCid, int $toCid): void
    {
        if (isset(self::$contexts[$fromCid])) {
            self::$contexts[$toCid] = self::$contexts[$fromCid];
            self::$parentMap[$toCid] = $fromCid;
        }
    }

    /**
     * Get the parent coroutine ID for the current coroutine.
     *
     * @return int|null
     */
    public static function getParentId(): ?int
    {
        $cid = Coroutine::getCid();
        
        if ($cid < 0) {
            return null;
        }

        return self::$parentMap[$cid] ?? null;
    }

    /**
     * Register a child coroutine with the current coroutine as its parent.
     *
     * @param int $childCid
     * @return void
     */
    public static function registerChild(int $childCid): void
    {
        $parentCid = Coroutine::getCid();
        
        if ($parentCid >= 0) {
            self::$parentMap[$childCid] = $parentCid;
            
            // Copy parent context to child
            if (isset(self::$contexts[$parentCid])) {
                self::$contexts[$childCid] = self::$contexts[$parentCid];
            }
        }
    }

    /**
     * Get the current coroutine ID.
     *
     * @return int Returns -1 if not in a coroutine
     */
    public static function getCurrentId(): int
    {
        return Coroutine::getCid();
    }

    /**
     * Check if currently executing in a coroutine.
     *
     * @return bool
     */
    public static function isInCoroutine(): bool
    {
        return Coroutine::getCid() > 0;
    }

    /**
     * Get statistics about coroutine context usage.
     * Useful for debugging and monitoring.
     *
     * @return array
     */
    public static function getStats(): array
    {
        return [
            'active_contexts' => count(self::$contexts),
            'parent_child_mappings' => count(self::$parentMap),
            'total_memory_usage' => memory_get_usage(true),
            'context_size_bytes' => strlen(serialize(self::$contexts)),
        ];
    }

    /**
     * Clear all contexts. Use with caution!
     * Mainly for testing or emergency cleanup.
     *
     * @return void
     */
    public static function clearAll(): void
    {
        self::$contexts = [];
        self::$parentMap = [];
    }
}
