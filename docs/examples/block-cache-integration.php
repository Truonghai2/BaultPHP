<?php

/**
 * Example: How to Integrate BlockCacheManager into Rendering
 * 
 * This shows how to update PageBlockRenderer and BlockRenderer
 * to use the new cache system
 */

namespace Modules\Cms\Domain\Services;

use Modules\Cms\Infrastructure\Models\Page;
use Modules\Cms\Infrastructure\Models\BlockInstance;
use Psr\Log\LoggerInterface;

// ============================================================================
// EXAMPLE 1: PageBlockRenderer with Caching
// ============================================================================

class PageBlockRendererWithCache
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly BlockClassRegistry $blockRegistry,
        private readonly BlockCacheManager $cacheManager // NEW!
    ) {
    }

    /**
     * Render page blocks with caching
     */
    public function renderPageBlocks(
        Page $page, 
        string $region = 'content', 
        ?array $context = null, 
        ?array $userRoles = null
    ): string {
        // 1. Try to get cached region (if no dynamic context)
        if (empty($context)) {
            $cached = $this->cacheManager->getPageRegion($page, $region, $userRoles);
            if ($cached !== null) {
                $this->logger->debug('Cache HIT for page region', [
                    'page_id' => $page->id,
                    'region' => $region,
                ]);
                return $cached;
            }
        }

        // 2. Cache MISS - render blocks
        $this->logger->debug('Cache MISS for page region', [
            'page_id' => $page->id,
            'region' => $region,
        ]);

        $pageBlocks = $page->blocksInRegion($region);

        if ($pageBlocks->isEmpty()) {
            return $this->renderLegacyBlockInstances($page, $region, $context, $userRoles);
        }

        $user = $this->getCurrentUser();

        // 3. Group blocks by type for batch data loading
        $blocksByType = $pageBlocks->groupBy(fn($pb) => $pb->blockType?->class);

        // 4. Try to get preloaded data from cache
        $preloadedData = [];
        foreach ($blocksByType as $class => $blocksOfSameType) {
            if (empty($class)) continue;

            $blockIds = $blocksOfSameType->pluck('id')->toArray();
            
            // Check cache first
            $cachedData = $this->cacheManager->getBlockData($class, $blockIds);
            
            if ($cachedData !== null) {
                $preloadedData = array_merge($preloadedData, $cachedData);
            } else {
                // Cache miss - fetch fresh data
                $blockInstance = $this->blockRegistry->getInstance($class);
                if ($blockInstance && method_exists($blockInstance, 'preloadData')) {
                    $dataForType = $blockInstance->preloadData($blocksOfSameType);
                    if (!empty($dataForType)) {
                        $preloadedData = array_merge($preloadedData, $dataForType);
                        
                        // Store in cache
                        $this->cacheManager->putBlockData($class, $blockIds, $dataForType);
                    }
                }
            }
        }

        // 5. Render blocks (with individual block caching)
        $htmlParts = [];
        
        foreach ($pageBlocks as $pageBlock) {
            try {
                // Check if this individual block is cached
                $blockHtml = null;
                if (empty($context) && $pageBlock->isCacheable()) {
                    $blockHtml = $this->cacheManager->getBlockOutput($pageBlock);
                }

                // Render if not cached
                if ($blockHtml === null) {
                    $blockContext = ['preloaded' => $preloadedData[$pageBlock->id] ?? []];
                    $blockHtml = $pageBlock->renderOptimized($user, $this->blockRegistry, $blockContext);
                    
                    // Cache individual block output
                    if (!empty($blockHtml) && empty($context) && $pageBlock->isCacheable()) {
                        $this->cacheManager->putBlockOutput($pageBlock, $blockHtml);
                    }
                }

                if ($blockHtml !== '') {
                    $htmlParts[] = $blockHtml;
                }
            } catch (\Throwable $e) {
                $this->logger->error('Failed to render page block', [
                    'page_id' => $page->id,
                    'block_id' => $pageBlock->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $html = implode("\n", $htmlParts);

        // 6. Cache the entire region (if no dynamic context)
        if (!empty($html) && empty($context)) {
            $this->cacheManager->putPageRegion($page, $region, $html, $userRoles);
        }

        return $html;
    }

    private function getCurrentUser() { /* ... */ }
    private function renderLegacyBlockInstances(...$args) { /* ... */ }
}

// ============================================================================
// EXAMPLE 2: BlockRenderer with Caching
// ============================================================================

class BlockRendererWithCache
{
    public function __construct(
        private readonly BlockRegistry $registry,
        private readonly BlockCacheManager $cacheManager, // NEW!
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Render a single block instance with caching
     */
    public function renderBlock(
        BlockInstance $instance, 
        ?array $userRoles = null, 
        ?array $context = null
    ): string {
        try {
            // 1. Check visibility
            if (!$instance->isVisibleTo($userRoles)) {
                return '';
            }

            $blockType = $instance->blockType;
            if (!$blockType) {
                return '';
            }

            $block = $this->registry->getBlock($blockType->name);
            if (!$block) {
                return '';
            }

            // 2. Try cache (only if cacheable and no dynamic context)
            if ($block->isCacheable() && empty($context)) {
                $cached = $this->cacheManager->getBlockOutput($instance);
                if ($cached !== null) {
                    $this->logger->debug('Cache HIT for block', [
                        'block_id' => $instance->id,
                    ]);
                    return $cached;
                }
            }

            // 3. Render fresh
            $blockContext = array_merge($context ?? [], [
                'title' => $instance->title,
                'content' => $instance->content,
            ]);
            
            $config = is_array($instance->config) ? $instance->config : [];
            $content = $block->render($config, $blockContext);
            $html = $this->wrapBlock($instance, $content, $blockType);

            // 4. Store in cache
            if ($block->isCacheable() && !empty($html) && empty($context)) {
                $ttl = $block->getCacheLifetime();
                $this->cacheManager->putBlockOutput($instance, $html, $ttl);
            }

            return $html;
            
        } catch (\Throwable $e) {
            $this->logger->error('Block render failed', [
                'block_id' => $instance->id,
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }

    private function wrapBlock(...$args) { /* ... */ }
}

// ============================================================================
// EXAMPLE 3: Custom Block with Cache Control
// ============================================================================

use Modules\Cms\Domain\Blocks\AbstractBlock;

class NewsBlock extends AbstractBlock
{
    /**
     * Enable caching for this block
     */
    public function isCacheable(): bool
    {
        return true;
    }

    /**
     * Set custom cache lifetime (5 minutes for news)
     */
    public function getCacheLifetime(): int
    {
        return 300; // 5 minutes
    }

    /**
     * Preload data for multiple instances (with caching)
     * 
     * Cache manager will automatically cache this data
     */
    public function preloadData($blocks): array
    {
        $newsIds = [];
        
        foreach ($blocks as $block) {
            $newsId = $block->content['news_id'] ?? null;
            if ($newsId) {
                $newsIds[] = $newsId;
            }
        }

        if (empty($newsIds)) {
            return [];
        }

        // Bulk fetch news articles
        $articles = \Modules\News\Models\Article::whereIn('id', $newsIds)->get();

        // Map to block IDs
        $preloaded = [];
        foreach ($blocks as $block) {
            $newsId = $block->content['news_id'] ?? null;
            if ($newsId) {
                $article = $articles->firstWhere('id', $newsId);
                if ($article) {
                    $preloaded[$block->id] = [
                        'article' => $article->toArray(),
                    ];
                }
            }
        }

        return $preloaded;
    }

    /**
     * Render using preloaded data
     */
    public function render(array $config = [], array $context = []): string
    {
        // Use preloaded data if available
        $article = $context['preloaded']['article'] ?? null;

        if (!$article && isset($context['news_id'])) {
            // Fallback to individual query
            $article = \Modules\News\Models\Article::find($context['news_id']);
        }

        if (!$article) {
            return '';
        }

        return view('blocks.news', ['article' => $article])->render();
    }
}

// ============================================================================
// EXAMPLE 4: Manual Cache Invalidation in Controller
// ============================================================================

use Modules\Cms\Http\Controllers\BaseController;
use Modules\Cms\Domain\Services\BlockCacheManager;

class PageManagementController extends BaseController
{
    public function __construct(
        private readonly BlockCacheManager $cacheManager
    ) {
    }

    /**
     * Update page - automatic cache invalidation via observer
     */
    public function update(int $id): Response
    {
        $page = Page::findOrFail($id);
        $page->update(request()->all());

        // Cache is automatically invalidated by PageObserver!
        
        return redirect()->route('admin.pages.index');
    }

    /**
     * Publish page - manual cache warming
     */
    public function publish(int $id): Response
    {
        $page = Page::findOrFail($id);
        $page->update(['status' => 'published']);

        // Warm up cache for published page
        $this->cacheManager->warmUpPage($page);

        return redirect()->route('admin.pages.index');
    }

    /**
     * Reorder blocks - invalidate specific region
     */
    public function reorderBlocks(int $pageId, string $region): Response
    {
        // ... update block weights ...

        // Invalidate just this region
        $this->cacheManager->invalidatePageRegion($pageId, $region);

        return response()->json(['success' => true]);
    }
}

