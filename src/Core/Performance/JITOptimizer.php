<?php

declare(strict_types=1);

namespace Core\Performance;

use Core\Support\Facades\Log;

/**
 * JIT Compilation với OPcache Enhancement
 *
 * Custom OPcache optimization với:
 * - Profile-based optimization
 * - Hot path detection
 * - Auto-optimization
 *
 * Features:
 * - Profile-based optimization
 * - Hot path detection
 * - Auto-optimization
 * - OPcache management
 */
class JITOptimizer
{
    protected array $profiles = [];
    protected array $hotPaths = [];
    protected array $optimizationCache = [];

    public function __construct(
        protected array $config = [],
    ) {
        $this->loadProfiles();
    }

    /**
     * Optimize OPcache
     */
    public function optimize(): void
    {
        if (!function_exists('opcache_get_status')) {
            Log::warning("OPcache is not available");
            return;
        }

        // Get OPcache status
        $status = opcache_get_status();
        if (!$status) {
            Log::warning("OPcache status unavailable");
            return;
        }

        // Profile-based optimization
        $this->optimizeBasedOnProfile();

        // Hot path detection
        $this->detectHotPaths();

        // Auto-optimization
        $this->autoOptimize();

        Log::info("OPcache optimization completed", [
            'cached_scripts' => $status['opcache_statistics']['num_cached_scripts'] ?? 0,
            'hit_rate' => $this->calculateHitRate($status),
        ]);
    }

    /**
     * Optimize based on profiling data
     */
    protected function optimizeBasedOnProfile(): void
    {
        foreach ($this->profiles as $file => $profile) {
            $accessCount = $profile['access_count'] ?? 0;
            $lastAccess = $profile['last_access'] ?? 0;
            
            // Optimize frequently accessed files
            if ($accessCount > ($this->config['min_access_count'] ?? 100)) {
                $this->preloadFile($file);
            }

            // Invalidate stale files
            if ($this->isStale($file, $lastAccess)) {
                $this->invalidateFile($file);
            }
        }
    }

    /**
     * Detect hot paths (frequently executed code paths)
     */
    protected function detectHotPaths(): void
    {
        $hotPathThreshold = $this->config['hot_path_threshold'] ?? 50;

        foreach ($this->profiles as $file => $profile) {
            $accessCount = $profile['access_count'] ?? 0;
            
            if ($accessCount >= $hotPathThreshold) {
                $this->hotPaths[$file] = [
                    'access_count' => $accessCount,
                    'last_access' => $profile['last_access'] ?? time(),
                    'optimized' => false,
                ];
            }
        }

        // Optimize hot paths
        foreach ($this->hotPaths as $file => $path) {
            if (!$path['optimized']) {
                $this->optimizeHotPath($file);
                $this->hotPaths[$file]['optimized'] = true;
            }
        }
    }

    /**
     * Optimize a hot path
     */
    protected function optimizeHotPath(string $file): void
    {
        if (!file_exists($file)) {
            return;
        }

        // Preload file to OPcache
        $this->preloadFile($file);

        // Optimize OPcache settings for this file
        if (function_exists('opcache_compile_file')) {
            opcache_compile_file($file);
        }

        Log::debug("Hot path optimized", ['file' => $file]);
    }

    /**
     * Auto-optimize based on heuristics
     */
    protected function autoOptimize(): void
    {
        $status = opcache_get_status();
        if (!$status) {
            return;
        }

        $memoryUsage = $status['memory_usage'] ?? [];
        $usedMemory = $memoryUsage['used_memory'] ?? 0;
        $freeMemory = $memoryUsage['free_memory'] ?? 0;
        $totalMemory = $usedMemory + $freeMemory;
        
        $memoryUsagePercent = $totalMemory > 0 ? ($usedMemory / $totalMemory) * 100 : 0;

        // If memory usage is high, optimize
        if ($memoryUsagePercent > ($this->config['memory_threshold'] ?? 80)) {
            $this->optimizeMemory();
        }

        // Optimize hit rate
        $hitRate = $this->calculateHitRate($status);
        if ($hitRate < ($this->config['min_hit_rate'] ?? 90)) {
            $this->optimizeHitRate();
        }
    }

    /**
     * Optimize memory usage
     */
    protected function optimizeMemory(): void
    {
        // Reset OPcache if memory usage is too high
        if (function_exists('opcache_reset')) {
            Log::info("OPcache memory usage high, resetting cache");
            opcache_reset();
        }
    }

    /**
     * Optimize hit rate
     */
    protected function optimizeHitRate(): void
    {
        // Preload frequently accessed files
        $filesToPreload = $this->getFilesToPreload();
        
        foreach ($filesToPreload as $file) {
            $this->preloadFile($file);
        }

        Log::info("Optimized hit rate by preloading files", [
            'files_preloaded' => count($filesToPreload),
        ]);
    }

    /**
     * Get files to preload
     */
    protected function getFilesToPreload(): array
    {
        // Sort by access count
        uasort($this->profiles, fn($a, $b) => ($b['access_count'] ?? 0) <=> ($a['access_count'] ?? 0));
        
        $limit = $this->config['preload_limit'] ?? 100;
        return array_slice(array_keys($this->profiles), 0, $limit);
    }

    /**
     * Preload a file into OPcache
     */
    protected function preloadFile(string $file): void
    {
        if (!file_exists($file)) {
            return;
        }

        if (function_exists('opcache_compile_file')) {
            opcache_compile_file($file);
        } elseif (function_exists('opcache_invalidate')) {
            // Invalidate to force recompilation
            opcache_invalidate($file, true);
        }

        // Track preloaded files
        $this->optimizationCache[$file] = time();
    }

    /**
     * Invalidate a file in OPcache
     */
    protected function invalidateFile(string $file): void
    {
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($file, true);
        }
    }

    /**
     * Check if file is stale
     */
    protected function isStale(string $file, int $lastAccess): bool
    {
        if (!file_exists($file)) {
            return true;
        }

        $fileMtime = filemtime($file);
        $staleThreshold = $this->config['stale_threshold'] ?? 3600; // 1 hour

        return (time() - max($fileMtime, $lastAccess)) > $staleThreshold;
    }

    /**
     * Record file access for profiling
     */
    public function recordAccess(string $file): void
    {
        if (!isset($this->profiles[$file])) {
            $this->profiles[$file] = [
                'access_count' => 0,
                'last_access' => 0,
            ];
        }

        $this->profiles[$file]['access_count']++;
        $this->profiles[$file]['last_access'] = time();

        // Persist profiles periodically
        if (count($this->profiles) % 100 === 0) {
            $this->saveProfiles();
        }
    }

    /**
     * Calculate OPcache hit rate
     */
    protected function calculateHitRate(array $status): float
    {
        $stats = $status['opcache_statistics'] ?? [];
        $hits = $stats['opcache_hits'] ?? 0;
        $misses = $stats['opcache_misses'] ?? 0;
        $total = $hits + $misses;

        if ($total === 0) {
            return 0.0;
        }

        return ($hits / $total) * 100;
    }

    /**
     * Get OPcache statistics
     */
    public function getStats(): array
    {
        $status = opcache_get_status();
        
        if (!$status) {
            return [
                'available' => false,
                'message' => 'OPcache not available',
            ];
        }

        $stats = $status['opcache_statistics'] ?? [];
        $memory = $status['memory_usage'] ?? [];
        $scripts = $status['scripts'] ?? [];

        return [
            'available' => true,
            'hit_rate' => $this->calculateHitRate($status),
            'cached_scripts' => $stats['num_cached_scripts'] ?? 0,
            'hits' => $stats['opcache_hits'] ?? 0,
            'misses' => $stats['opcache_misses'] ?? 0,
            'memory_used' => $memory['used_memory'] ?? 0,
            'memory_free' => $memory['free_memory'] ?? 0,
            'memory_usage_percent' => $this->calculateMemoryUsagePercent($memory),
            'hot_paths' => count($this->hotPaths),
            'profiled_files' => count($this->profiles),
        ];
    }

    /**
     * Calculate memory usage percent
     */
    protected function calculateMemoryUsagePercent(array $memory): float
    {
        $used = $memory['used_memory'] ?? 0;
        $free = $memory['free_memory'] ?? 0;
        $total = $used + $free;

        if ($total === 0) {
            return 0.0;
        }

        return ($used / $total) * 100;
    }

    /**
     * Load profiles from cache
     */
    protected function loadProfiles(): void
    {
        $cachePath = storage_path('framework/cache/opcache_profiles.php');
        
        if (file_exists($cachePath)) {
            $this->profiles = require $cachePath;
        }
    }

    /**
     * Save profiles to cache
     */
    protected function saveProfiles(): void
    {
        $cachePath = storage_path('framework/cache/opcache_profiles.php');
        $cacheDir = dirname($cachePath);
        
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        file_put_contents($cachePath, '<?php return ' . var_export($this->profiles, true) . ';');
    }

    /**
     * Reset optimization cache
     */
    public function reset(): void
    {
        $this->profiles = [];
        $this->hotPaths = [];
        $this->optimizationCache = [];
        
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
    }
}
