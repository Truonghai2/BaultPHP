<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\Services;

use Modules\Cms\Infrastructure\Models\BlockInstance;
use Modules\Cms\Infrastructure\Models\BlockRegion;
use Modules\Cms\Infrastructure\Models\Page;
use Psr\Log\LoggerInterface;

/**
 * Page Block Renderer (OPTIMIZED)
 *
 * Renders blocks directly from page_blocks table with comprehensive optimizations:
 *
 * PERFORMANCE FEATURES:
 * - Block class registry for instance caching (singleton pattern)
 * - User instance caching per request
 * - Array buffer for efficient HTML building
 * - Optional region-level caching
 * - Preloading support for batch data fetching
 * - Lazy loading of block dependencies
 *
 * CACHING STRATEGY:
 * - Level 1: Block output cache (per block instance)
 * - Level 2: Region cache (all blocks in a region)
 * - Level 3: Preloaded data cache (shared data)
 *
 * ERROR HANDLING:
 * - Graceful degradation on block failures
 * - Detailed error logging
 * - Debug mode HTML comments
 */
class PageBlockRenderer
{
    /**
     * Cached user instance for the request
     */
    private ?\Modules\User\Infrastructure\Models\User $cachedUser = null;
    private bool $userCached = false;

    /**
     * Enable/disable region-level caching
     */
    private bool $enableRegionCache = true;

    /**
     * Cache statistics for debugging
     */
    private array $cacheStats = [
        'hits' => 0,
        'misses' => 0,
        'errors' => 0,
    ];

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly BlockClassRegistry $blockRegistry,
        private readonly ?BlockCacheManager $cacheManager = null,
    ) {
    }

    /**
     * Render all blocks for a page in a specific region
     *
     * OPTIMIZATION STRATEGY:
     * 1. Check region cache first (if enabled)
     * 2. Preload data for all blocks of same type in one query
     * 3. Render each block with preloaded context
     * 4. Cache region output if successful
     *
     * @param Page $page
     * @param string $region Region name (e.g., 'hero', 'content', 'sidebar')
     * @param array|null $context Additional context for rendering
     * @param array|null $userRoles User roles for visibility checks (deprecated - uses auth()->user())
     * @return string Rendered HTML
     */
    public function renderPageBlocks(Page $page, string $region = 'content', ?array $context = null, ?array $userRoles = null): string
    {
        // Try region cache first
        if ($this->enableRegionCache && $this->cacheManager) {
            $cached = $this->cacheManager->getPageRegion($page, $region, $userRoles);
            if ($cached !== null) {
                $this->cacheStats['hits']++;
                return $cached;
            }
            $this->cacheStats['misses']++;
        }

        // Performance optimization: Pass user for visibility pre-filtering
        $user = $this->getCurrentUser();
        $pageBlocks = $page->blocksInRegion($region, $user);

        // FALLBACK: If no page_blocks found, try legacy block_instances
        if ($pageBlocks->isEmpty()) {
            return $this->renderLegacyBlockInstances($page, $region, $context, $userRoles);
        }

        // User already retrieved above for blocksInRegion()

        // Preload data for performance
        $preloadedData = $this->preloadBlockData($pageBlocks);

        // Render blocks
        $htmlParts = $this->renderBlocks($pageBlocks, $user, $preloadedData, $page->id, $region);

        $html = implode('', $htmlParts);

        // Cache the region output
        if ($this->enableRegionCache && $this->cacheManager && !empty($html)) {
            $this->cacheManager->putPageRegion($page, $region, $html, $userRoles);
        }

        return $html;
    }

    /**
     * Preload data for all blocks efficiently
     *
     * Groups blocks by type and calls preloadData() once per type
     *
     * @param \Core\Support\Collection $pageBlocks
     * @return array Preloaded data indexed by block ID
     */
    private function preloadBlockData(\Core\Support\Collection $pageBlocks): array
    {
        $startTime = microtime(true);
        $blocksByType = $pageBlocks->groupBy(fn ($pb) => $pb->blockType?->class);
        $preloadedData = [];
        $stats = [];

        foreach ($blocksByType as $class => $blocksOfSameType) {
            if (empty($class)) {
                continue;
            }

            try {
                $typeStartTime = microtime(true);
                $blockInstance = $this->blockRegistry->getInstance($class);

                // Verify block has preloadData method
                if (!$blockInstance || !method_exists($blockInstance, 'preloadData')) {
                    $stats[$class] = [
                        'skipped' => true,
                        'reason' => 'no_preload_method',
                        'blocks_count' => count($blocksOfSameType),
                    ];
                    continue;
                }

                $collection = $this->ensureCoreCollection($blocksOfSameType);

                // Try cache first
                if ($this->cacheManager && $this->enableRegionCache) {
                    $blockIds = $collection->pluck('id')->all();
                    $cachedData = $this->cacheManager->getBlockData($class, $blockIds);

                    if ($cachedData !== null) {
                        $preloadedData = array_merge($preloadedData, $cachedData);
                        $stats[$class] = [
                            'cached' => true,
                            'blocks_count' => count($cachedData),
                            'time_ms' => (microtime(true) - $typeStartTime) * 1000,
                        ];
                        continue;
                    }
                }

                // Fetch fresh data
                $dataForType = $blockInstance->preloadData($collection);
                $typeTime = (microtime(true) - $typeStartTime) * 1000;

                if (!empty($dataForType)) {
                    $preloadedData = array_merge($preloadedData, $dataForType);

                    $stats[$class] = [
                        'cached' => false,
                        'blocks_count' => count($dataForType),
                        'time_ms' => $typeTime,
                    ];

                    // Cache the data
                    if ($this->cacheManager && is_array($dataForType) && $this->enableRegionCache) {
                        $blockIds = array_keys($dataForType);
                        $this->cacheManager->putBlockData($class, $blockIds, $dataForType);
                    }
                }

            } catch (\Throwable $e) {
                $this->logger->warning('Failed to preload data for block type', [
                    'class' => $class,
                    'error' => $e->getMessage(),
                ]);
                $this->cacheStats['errors']++;
                $stats[$class] = [
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Log preload stats in debug mode
        if (config('app.debug')) {
            $totalTime = (microtime(true) - $startTime) * 1000;
            $this->logger->debug('Block preload performance', [
                'total_time_ms' => round($totalTime, 2),
                'types_count' => count($blocksByType),
                'blocks_total' => $pageBlocks->count(),
                'by_type' => $stats,
            ]);
        }

        return $preloadedData;
    }

    /**
     * Render a collection of blocks
     *
     * OPTIMIZATION: Check individual block output cache before rendering
     *
     * @param \Core\Support\Collection $pageBlocks
     * @param \Modules\User\Infrastructure\Models\User|null $user
     * @param array $preloadedData
     * @param int $pageId
     * @param string $region
     * @return array Array of HTML strings
     */
    private function renderBlocks(
        \Core\Support\Collection $pageBlocks,
        ?\Modules\User\Infrastructure\Models\User $user,
        array $preloadedData,
        int $pageId,
        string $region,
    ): array {
        $htmlParts = [];
        $toCache = []; // Batch cache operations

        foreach ($pageBlocks as $pageBlock) {
            try {
                // Performance optimization: Check individual block output cache first
                if ($this->cacheManager) {
                    $cached = $this->cacheManager->getBlockOutput($pageBlock);
                    if ($cached !== null) {
                        $htmlParts[] = $cached;
                        $this->cacheStats['hits']++;
                        continue;
                    }
                    $this->cacheStats['misses']++;
                }

                $blockContext = [
                    'preloaded' => $preloadedData[$pageBlock->id] ?? [],
                    'page_id' => $pageId,
                    'region' => $region,
                ];

                $rendered = $pageBlock->renderOptimized($user, $this->blockRegistry, $blockContext);

                if ($rendered !== '') {
                    $htmlParts[] = $rendered;

                    // Queue for batch caching (performance optimization)
                    if ($this->cacheManager) {
                        $toCache[] = ['block' => $pageBlock, 'html' => $rendered];
                    }
                }

            } catch (\Throwable $e) {
                $this->handleBlockRenderError($e, $pageBlock, $pageId, $region, $htmlParts);
            }
        }

        // Batch cache all rendered blocks (performance optimization)
        if (!empty($toCache) && $this->cacheManager) {
            foreach ($toCache as $item) {
                $this->cacheManager->putBlockOutput($item['block'], $item['html']);
            }
        }

        return $htmlParts;
    }

    /**
     * Handle block render error gracefully
     *
     * @param \Throwable $e
     * @param mixed $pageBlock
     * @param int $pageId
     * @param string $region
     * @param array &$htmlParts
     * @return void
     */
    private function handleBlockRenderError(
        \Throwable $e,
        $pageBlock,
        int $pageId,
        string $region,
        array &$htmlParts,
    ): void {
        $this->cacheStats['errors']++;

        $this->logger->error('Failed to render page block', [
            'page_id' => $pageId,
            'block_id' => $pageBlock->id,
            'block_type_id' => $pageBlock->block_type_id,
            'region' => $region,
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        if (config('app.debug')) {
            $htmlParts[] = sprintf(
                "<!-- Block #%d render error: %s (in %s:%d) -->\n",
                $pageBlock->id,
                htmlspecialchars($e->getMessage()),
                basename($e->getFile()),
                $e->getLine(),
            );
        }
    }

    /**
     * Ensure collection is Core\Support\Collection
     *
     * @param mixed $collection
     * @return \Core\Support\Collection
     */
    private function ensureCoreCollection($collection): \Core\Support\Collection
    {
        if ($collection instanceof \Core\Support\Collection) {
            return $collection;
        }

        if ($collection instanceof \Illuminate\Support\Collection) {
            return new \Core\Support\Collection($collection->all());
        }

        if (is_array($collection)) {
            return new \Core\Support\Collection($collection);
        }

        return new \Core\Support\Collection([]);
    }

    /**
     * LEGACY SUPPORT: Render blocks from block_instances table
     *
     * @param Page $page
     * @param string $region Region name
     * @param array|null $context
     * @param array|null $userRoles
     * @return string Rendered HTML
     */
    private function renderLegacyBlockInstances(Page $page, string $region, ?array $context, ?array $userRoles): string
    {
        // Find region by name
        $regionModel = BlockRegion::where('name', $region)->first();

        if (!$regionModel) {
            return '';
        }

        // Get block instances for this page and region
        $blockInstances = BlockInstance::where('context_type', 'page')
            ->where('context_id', $page->id)
            ->where('region_id', $regionModel->id)
            ->where('visible', true)
            ->orderBy('weight')
            ->get();

        if ($blockInstances->isEmpty()) {
            return '';
        }

        $user = $this->getCurrentUser();
        $htmlParts = [];

        foreach ($blockInstances as $instance) {
            try {
                if ($instance->isVisibleTo($user)) {
                    $rendered = $instance->render($user);
                    if ($rendered !== '') {
                        $htmlParts[] = $rendered;
                    }
                }
            } catch (\Throwable $e) {
                $this->logger->error('Failed to render legacy block instance', [
                    'page_id' => $page->id,
                    'instance_id' => $instance->id,
                    'region' => $region,
                    'error' => $e->getMessage(),
                ]);

                if (config('app.debug')) {
                    $htmlParts[] = "<!-- Legacy Block #{$instance->id} render error: {$e->getMessage()} -->\n";
                }
            }
        }

        if (config('app.debug') && !empty($htmlParts)) {
            array_unshift($htmlParts, "<!-- Rendering legacy block_instances for page #{$page->id}, region '{$region}' -->\n");
        }

        return implode('', $htmlParts);
    }

    /**
     * Get current user (cached for request lifecycle)
     *
     * @return \Modules\User\Infrastructure\Models\User|null
     */
    private function getCurrentUser(): ?\Modules\User\Infrastructure\Models\User
    {
        if (!$this->userCached) {
            $this->cachedUser = auth()->check() ? auth()->user() : null;
            $this->userCached = true;
        }

        return $this->cachedUser;
    }

    /**
     * Render global blocks for a region (blocks shown on all pages)
     *
     * These are stored in block_instances with context_type='global'
     *
     * @param string $region Region name
     * @param \Modules\User\Infrastructure\Models\User|null $user Current user
     * @return string Rendered HTML
     */
    public function renderGlobalBlocks(string $region, $user = null): string
    {
        $globalRegion = BlockRegion::where('name', $region)->first();

        if (!$globalRegion) {
            return '';
        }

        // Get global block instances
        $globalBlocks = BlockInstance::where('context_type', 'global')
            ->where('region_id', $globalRegion->id)
            ->where('visible', true)
            ->orderBy('weight')
            ->get();

        if ($globalBlocks->isEmpty()) {
            return '';
        }

        $html = '';
        foreach ($globalBlocks as $blockInstance) {
            try {
                $html .= $blockInstance->render($user);
            } catch (\Throwable $e) {
                $this->logger->error('Failed to render global block', [
                    'block_id' => $blockInstance->id,
                    'region' => $region,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $html;
    }

    /**
     * Render both page-specific AND global blocks for a region
     *
     * @param Page $page
     * @param string $region
     * @param \Modules\User\Infrastructure\Models\User|null $user
     * @return string
     */
    public function renderAllBlocks(Page $page, string $region = 'content', $user = null): string
    {
        $pageHtml = $this->renderPageBlocks($page, $region, $user);
        $globalHtml = $this->renderGlobalBlocks($region, $user);

        return $pageHtml . $globalHtml;
    }

    /**
     * Get all regions used by a page
     *
     * @param Page $page
     * @return array Array of region names
     */
    public function getPageRegions(Page $page): array
    {
        return $page->getRegions();
    }

    /**
     * Check if page has blocks in a region
     *
     * @param Page $page
     * @param string $region
     * @return bool
     */
    public function pageHasBlocksInRegion(Page $page, string $region): bool
    {
        return $page->blocksInRegion($region)->isNotEmpty();
    }

    // ============================================================================
    // CACHE CONTROL METHODS
    // ============================================================================

    /**
     * Enable region-level caching
     *
     * @return self
     */
    public function withCache(): self
    {
        $this->enableRegionCache = true;
        return $this;
    }

    /**
     * Disable region-level caching (useful for admin pages)
     *
     * @return self
     */
    public function withoutCache(): self
    {
        $this->enableRegionCache = false;
        return $this;
    }

    /**
     * Clear cache for a specific page and region
     *
     * @param Page $page
     * @param string $region
     * @return void
     */
    public function clearRegionCache(Page $page, string $region): void
    {
        if ($this->cacheManager) {
            $this->cacheManager->invalidatePageRegion($page->id, $region);
        }
    }

    /**
     * Clear all caches for a page
     *
     * @param Page $page
     * @return void
     */
    public function clearPageCache(Page $page): void
    {
        if ($this->cacheManager) {
            $this->cacheManager->invalidatePage($page);
        }
    }

    /**
     * Warm up cache for a page (preload common regions)
     *
     * @param Page $page
     * @param array|null $userRoles
     * @return void
     */
    public function warmUpCache(Page $page, ?array $userRoles = null): void
    {
        if ($this->cacheManager) {
            $this->cacheManager->warmUpPage($page, $userRoles);
        }
    }

    // ============================================================================
    // STATISTICS AND DEBUGGING
    // ============================================================================

    /**
     * Get cache statistics for the current request
     *
     * @return array
     */
    public function getCacheStats(): array
    {
        return $this->cacheStats;
    }

    /**
     * Get comprehensive statistics
     *
     * @return array
     */
    public function getStats(): array
    {
        $stats = [
            'cache' => $this->cacheStats,
            'registry' => $this->blockRegistry->getStats(),
        ];

        if ($this->cacheManager) {
            $stats['cache_manager'] = $this->cacheManager->getStats();
        }

        return $stats;
    }

    /**
     * Reset cache statistics
     *
     * @return void
     */
    public function resetStats(): void
    {
        $this->cacheStats = [
            'hits' => 0,
            'misses' => 0,
            'errors' => 0,
        ];
    }

    /**
     * Get cache hit ratio
     *
     * @return float Between 0 and 1, or 0 if no requests
     */
    public function getCacheHitRatio(): float
    {
        $total = $this->cacheStats['hits'] + $this->cacheStats['misses'];

        if ($total === 0) {
            return 0.0;
        }

        return $this->cacheStats['hits'] / $total;
    }
}
