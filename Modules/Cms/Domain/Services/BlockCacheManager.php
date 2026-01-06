<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\Services;

use Core\Cache\CacheManager;
use Modules\Cms\Infrastructure\Models\BlockInstance;
use Modules\Cms\Infrastructure\Models\BlockType;
use Modules\Cms\Infrastructure\Models\Page;
use Modules\Cms\Infrastructure\Models\PageBlock;
use Psr\Log\LoggerInterface;

/**
 * Block Cache Manager
 *
 * Centralized cache invalidation strategy for the block system
 *
 * CACHE LAYERS:
 * 1. Block Output Cache - Rendered HTML per block instance
 * 2. Page Region Cache - Full region HTML per page
 * 3. Block Class Registry - In-memory PHP object cache
 * 4. Block Data Cache - Pre-fetched data for blocks
 * 5. Block Type Metadata - Block type configuration
 */
class BlockCacheManager
{
    private const CACHE_PREFIX = 'blocks';
    private const PAGE_CACHE_PREFIX = 'page_blocks';
    private const BLOCK_OUTPUT_PREFIX = 'block_output';
    private const BLOCK_DATA_PREFIX = 'block_data';
    private const REGION_PREFIX = 'region';

    // Cache TTL (in seconds)
    private const DEFAULT_TTL = 3600; // 1 hour
    private const PAGE_CACHE_TTL = 1800; // 30 minutes
    private const BLOCK_DATA_TTL = 600; // 10 minutes

    public function __construct(
        private readonly CacheManager $cache,
        private readonly BlockClassRegistry $registry,
        private readonly LoggerInterface $logger,
    ) {
    }

    // ============================================================================
    // CACHE KEY GENERATION
    // ============================================================================

    /**
     * Generate cache key for a block instance output
     */
    public function getBlockOutputKey(BlockInstance $instance): string
    {
        return sprintf(
            '%s:%s:%d:v%s',
            self::BLOCK_OUTPUT_PREFIX,
            $instance->block_type_id,
            $instance->id,
            md5(serialize($instance->config) . $instance->updated_at),
        );
    }

    /**
     * Generate cache key for a page block output
     */
    public function getPageBlockOutputKey(PageBlock $pageBlock): string
    {
        return sprintf(
            '%s:%s:%d:%d:v%s',
            self::BLOCK_OUTPUT_PREFIX,
            'page',
            $pageBlock->page_id,
            $pageBlock->id,
            md5(serialize($pageBlock->content) . $pageBlock->updated_at),
        );
    }

    /**
     * Generate cache key for a page region
     */
    public function getPageRegionKey(Page $page, string $region, ?array $userRoles = null): string
    {
        $roleHash = $userRoles ? md5(serialize($userRoles)) : 'guest';

        return sprintf(
            '%s:%s:%d:%s:%s:v%s',
            self::PAGE_CACHE_PREFIX,
            self::REGION_PREFIX,
            $page->id,
            $region,
            $roleHash,
            $page->updated_at,
        );
    }

    /**
     * Generate cache key for block preloaded data
     */
    public function getBlockDataKey(string $blockClass, array $blockIds): string
    {
        return sprintf(
            '%s:%s:%s',
            self::BLOCK_DATA_PREFIX,
            md5($blockClass),
            md5(serialize($blockIds)),
        );
    }

    /**
     * Generate cache key for global region
     */
    public function getGlobalRegionKey(string $region, ?array $userRoles = null): string
    {
        $roleHash = $userRoles ? md5(serialize($userRoles)) : 'guest';

        return sprintf(
            '%s:%s:%s:%s',
            self::CACHE_PREFIX,
            self::REGION_PREFIX,
            $region,
            $roleHash,
        );
    }

    // ============================================================================
    // CACHE RETRIEVAL
    // ============================================================================

    /**
     * Get cached block output
     */
    public function getBlockOutput(BlockInstance|PageBlock $block): ?string
    {
        $key = $block instanceof PageBlock
            ? $this->getPageBlockOutputKey($block)
            : $this->getBlockOutputKey($block);

        return $this->cache->get($key);
    }

    /**
     * Get cached page region
     */
    public function getPageRegion(Page $page, string $region, ?array $userRoles = null): ?string
    {
        $key = $this->getPageRegionKey($page, $region, $userRoles);
        return $this->cache->get($key);
    }

    /**
     * Get cached block data
     */
    public function getBlockData(string $blockClass, array $blockIds): ?array
    {
        $key = $this->getBlockDataKey($blockClass, $blockIds);
        $data = $this->cache->get($key);

        return is_array($data) ? $data : null;
    }

    // ============================================================================
    // CACHE STORAGE
    // ============================================================================

    /**
     * Store block output in cache
     */
    public function putBlockOutput(BlockInstance|PageBlock $block, string $html, ?int $ttl = null): void
    {
        $key = $block instanceof PageBlock
            ? $this->getPageBlockOutputKey($block)
            : $this->getBlockOutputKey($block);

        $this->cache->put($key, $html, $ttl ?? self::DEFAULT_TTL);
    }

    /**
     * Store page region in cache
     */
    public function putPageRegion(Page $page, string $region, string $html, ?array $userRoles = null, ?int $ttl = null): void
    {
        $key = $this->getPageRegionKey($page, $region, $userRoles);
        $this->cache->put($key, $html, $ttl ?? self::PAGE_CACHE_TTL);
    }

    /**
     * Store block preloaded data
     */
    public function putBlockData(string $blockClass, array $blockIds, array $data, ?int $ttl = null): void
    {
        $key = $this->getBlockDataKey($blockClass, $blockIds);
        $this->cache->put($key, $data, $ttl ?? self::BLOCK_DATA_TTL);
    }

    // ============================================================================
    // CACHE INVALIDATION - The Core Strategy
    // ============================================================================

    /**
     * STRATEGY 1: Invalidate when a block is updated
     *
     * What to clear:
     * - The block's own output cache
     * - All page regions containing this block
     * - Global regions containing this block
     */
    public function invalidateBlock(BlockInstance|PageBlock $block): void
    {
        $this->logger->debug('Invalidating block cache', [
            'block_id' => $block->id,
            'type' => $block instanceof PageBlock ? 'PageBlock' : 'BlockInstance',
        ]);

        // 1. Clear block output cache
        $this->clearBlockOutput($block);

        // 2. Clear related regions
        if ($block instanceof PageBlock) {
            $this->invalidatePageRegion($block->page_id, $block->region);
        } elseif ($block instanceof BlockInstance) {
            if ($block->context_type === 'page' && $block->context_id) {
                $region = $block->region?->name;
                if ($region) {
                    $this->invalidatePageRegion($block->context_id, $region);
                }
            } else {
                // Global block - invalidate global region
                $region = $block->region?->name;
                if ($region) {
                    $this->invalidateGlobalRegion($region);
                }
            }
        }

        // 3. Clear block data cache for this block type
        if ($block->blockType) {
            $this->clearBlockTypeData($block->blockType);
        }
    }

    /**
     * STRATEGY 2: Invalidate when a page is updated
     *
     * What to clear:
     * - All regions for this page
     * - Block data cache for blocks on this page
     */
    public function invalidatePage(Page $page): void
    {
        $this->logger->debug('Invalidating page cache', ['page_id' => $page->id]);

        // Clear all region caches for this page
        $regions = ['header', 'hero', 'content', 'sidebar-left', 'sidebar', 'footer'];

        foreach ($regions as $region) {
            $this->invalidatePageRegion($page->id, $region);
        }

        // Clear block outputs for all blocks on this page
        $pageBlocks = PageBlock::where('page_id', $page->id)->get();
        foreach ($pageBlocks as $block) {
            $this->clearBlockOutput($block);
        }
    }

    /**
     * STRATEGY 3: Invalidate when a block type is updated
     *
     * What to clear:
     * - All instances of this block type
     * - Block class registry
     * - Block data cache
     */
    public function invalidateBlockType(BlockType $blockType): void
    {
        $this->logger->info('Invalidating block type cache', [
            'block_type_id' => $blockType->id,
            'name' => $blockType->name,
        ]);

        // Clear class registry
        $this->registry->clear();

        // Clear all instances of this block type
        $instances = BlockInstance::where('block_type_id', $blockType->id)->get();
        foreach ($instances as $instance) {
            $this->clearBlockOutput($instance);
        }

        $pageBlocks = PageBlock::where('block_type_id', $blockType->id)->get();
        foreach ($pageBlocks as $block) {
            $this->clearBlockOutput($block);
        }

        // Clear block data cache
        $this->clearBlockTypeData($blockType);

        // Clear all page caches that might contain this block
        $this->invalidateAllPageCaches();
    }

    /**
     * STRATEGY 4: Invalidate a specific region on a page
     */
    public function invalidatePageRegion(int $pageId, string $region): void
    {
        // Clear for all possible user role combinations
        // Pattern: page_blocks:region:{pageId}:{region}:*
        $pattern = sprintf('%s:%s:%d:%s:*', self::PAGE_CACHE_PREFIX, self::REGION_PREFIX, $pageId, $region);

        $this->cache->forgetPattern($pattern);

        $this->logger->debug('Invalidated page region', [
            'page_id' => $pageId,
            'region' => $region,
        ]);
    }

    /**
     * STRATEGY 5: Invalidate global region
     */
    public function invalidateGlobalRegion(string $region): void
    {
        $pattern = sprintf('%s:%s:%s:*', self::CACHE_PREFIX, self::REGION_PREFIX, $region);
        $this->cache->forgetPattern($pattern);

        $this->logger->debug('Invalidated global region', ['region' => $region]);
    }

    /**
     * Clear block output cache
     */
    private function clearBlockOutput(BlockInstance|PageBlock $block): void
    {
        $key = $block instanceof PageBlock
            ? $this->getPageBlockOutputKey($block)
            : $this->getBlockOutputKey($block);

        $this->cache->forget($key);
    }

    /**
     * Clear block type data cache
     */
    private function clearBlockTypeData(BlockType $blockType): void
    {
        if ($blockType->class) {
            $pattern = sprintf('%s:%s:*', self::BLOCK_DATA_PREFIX, md5($blockType->class));
            $this->cache->forgetPattern($pattern);
        }
    }

    /**
     * Clear all page caches (nuclear option)
     */
    private function invalidateAllPageCaches(): void
    {
        $this->cache->forgetPattern(self::PAGE_CACHE_PREFIX . ':*');
    }

    // ============================================================================
    // BATCH OPERATIONS
    // ============================================================================

    /**
     * Clear all block-related caches (nuclear option for deployment)
     */
    public function clearAll(): void
    {
        $this->logger->warning('Clearing ALL block caches');

        $this->cache->forgetPattern(self::CACHE_PREFIX . ':*');
        $this->cache->forgetPattern(self::PAGE_CACHE_PREFIX . ':*');
        $this->cache->forgetPattern(self::BLOCK_OUTPUT_PREFIX . ':*');
        $this->cache->forgetPattern(self::BLOCK_DATA_PREFIX . ':*');

        $this->registry->clear();
    }

    /**
     * Warm up cache for a page (preload all regions)
     */
    public function warmUpPage(Page $page, ?array $userRoles = null): void
    {
        $this->logger->info('Warming up page cache', ['page_id' => $page->id]);

        $renderer = app(PageBlockRenderer::class);
        $regions = ['header', 'hero', 'content', 'sidebar-left', 'sidebar', 'footer'];

        foreach ($regions as $region) {
            $html = $renderer->renderPageBlocks($page, $region, null, $userRoles);

            if (!empty($html)) {
                $this->putPageRegion($page, $region, $html, $userRoles);
            }
        }
    }

    /**
     * Get cache statistics
     */
    public function getStats(): array
    {
        return [
            'registry' => $this->registry->getStats(),
            'cache_driver' => get_class($this->cache),
        ];
    }
}
