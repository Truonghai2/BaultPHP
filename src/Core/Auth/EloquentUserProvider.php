<?php

namespace Core\Auth;

use Core\Application;
use Core\Cache\CacheManager;
use Core\Contracts\Auth\Authenticatable;
use Core\Contracts\Auth\UserProvider;

/**
 * Optimized EloquentUserProvider with multi-level caching.
 *
 * PERFORMANCE OPTIMIZATIONS:
 * - L1: Request-level cache (fastest, no I/O)
 * - L2: APCu cache (shared memory, 60s TTL)
 * - L3: Redis cache (persistent, 5min TTL)
 * - L4: Database query (fallback)
 */
class EloquentUserProvider implements UserProvider
{
    protected string $model;

    /**
     * Request-level cache to avoid duplicate database queries
     * Cache is automatically cleared between requests
     */
    private static array $requestCache = [];

    /**
     * Cache manager for persistent caching (Redis/file)
     */
    private ?CacheManager $cacheManager = null;

    /**
     * Application instance for accessing cache manager
     */
    private ?Application $app = null;

    public function __construct(string $model, ?Application $app = null)
    {
        $this->model = $model;
        $this->app = $app;

        // Get cache manager if available
        if ($app && $app->has(CacheManager::class)) {
            $this->cacheManager = $app->make(CacheManager::class);
        }
    }

    /**
     * Retrieve a user by their unique identifier.
     *
     * PERFORMANCE: Multi-level caching (L1 → L2 → L3 → DB)
     */
    public function retrieveById($identifier): ?Authenticatable
    {
        // L1: Check request cache first (fastest, no I/O)
        $l1Key = "user:id:{$identifier}";
        if (isset(self::$requestCache[$l1Key])) {
            return self::$requestCache[$l1Key];
        }

        // L2: Check APCu cache (shared memory, 60s TTL)
        if (function_exists('apcu_fetch')) {
            $success = false;
            $l2Data = \apcu_fetch("auth:user:{$identifier}", $success);
            if ($success && $l2Data instanceof Authenticatable) {
                self::$requestCache[$l1Key] = $l2Data;
                return $l2Data;
            }
        }

        // L3: Check persistent cache (Redis/file, 5min TTL)
        if ($this->cacheManager) {
            $l3Key = "auth:user:{$identifier}";
            $l3Data = $this->cacheManager->get($l3Key);
            if ($l3Data instanceof Authenticatable) {
                // Store in L1 and L2 for next time
                self::$requestCache[$l1Key] = $l3Data;
                if (function_exists('apcu_store')) {
                    \apcu_store("auth:user:{$identifier}", $l3Data, 60);
                }
                return $l3Data;
            }
        }

        // L4: Database query (fallback)
        $user = $this->model::find($identifier);

        // Cache the result at all levels
        if ($user) {
            self::$requestCache[$l1Key] = $user;

            // Store in L2 (APCu)
            if (function_exists('apcu_store')) {
                \apcu_store("auth:user:{$identifier}", $user, 60);
            }

            // Store in L3 (Redis/file)
            if ($this->cacheManager) {
                $this->cacheManager->put($l3Key, $user, 300); // 5 minutes
            }
        }

        return $user;
    }

    /**
     * Retrieve a user by the given credentials.
     *
     * PERFORMANCE: Multi-level caching with credential-based keys
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        if (empty($credentials) || (count($credentials) === 1 && array_key_exists('password', $credentials))) {
            return null;
        }

        // Generate cache key based on credentials (excluding password)
        $credKey = md5(json_encode(array_diff_key($credentials, ['password' => ''])));
        $cacheKey = "cred:{$credKey}";

        // L1: Check request cache first
        if (isset(self::$requestCache[$cacheKey])) {
            return self::$requestCache[$cacheKey];
        }

        // L2: Check APCu cache (shorter TTL for credentials - 30s)
        if (function_exists('apcu_fetch')) {
            $success = false;
            $l2Data = \apcu_fetch("auth:{$cacheKey}", $success);
            if ($success && $l2Data instanceof Authenticatable) {
                self::$requestCache[$cacheKey] = $l2Data;
                return $l2Data;
            }
        }

        // L3: Check persistent cache (shorter TTL - 2min)
        if ($this->cacheManager) {
            $l3Data = $this->cacheManager->get("auth:{$cacheKey}");
            if ($l3Data instanceof Authenticatable) {
                self::$requestCache[$cacheKey] = $l3Data;
                if (function_exists('apcu_store')) {
                    \apcu_store("auth:{$cacheKey}", $l3Data, 30);
                }
                return $l3Data;
            }
        }

        // L4: Database query
        $query = $this->model::query();

        foreach ($credentials as $key => $value) {
            if ($key !== 'password') {
                $query->where($key, $value);
            }
        }

        $user = $query->first();

        // Cache the result at all levels
        if ($user) {
            self::$requestCache[$cacheKey] = $user;

            // Store in L2 (APCu) - shorter TTL for credentials
            if (function_exists('apcu_store')) {
                \apcu_store("auth:{$cacheKey}", $user, 30);
            }

            // Store in L3 (Redis/file) - shorter TTL for credentials
            if ($this->cacheManager) {
                $this->cacheManager->put("auth:{$cacheKey}", $user, 120); // 2 minutes
            }
        }

        return $user;
    }

    /**
     * Clear cache for a specific user.
     * Should be called when user data is updated.
     */
    public function clearCache($identifier): void
    {
        $l1Key = "user:id:{$identifier}";
        unset(self::$requestCache[$l1Key]);

        // Clear L2 (APCu)
        if (function_exists('apcu_delete')) {
            \apcu_delete("auth:user:{$identifier}");
        }

        // Clear L3 (Redis/file)
        if ($this->cacheManager) {
            $this->cacheManager->forget("auth:user:{$identifier}");
        }
    }

    /**
     * Clear all caches (useful for testing or maintenance).
     */
    public static function clearAllCaches(): void
    {
        self::$requestCache = [];
    }
}
