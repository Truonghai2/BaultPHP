<?php

declare(strict_types=1);

namespace Modules\Cms\Http\Controllers;

use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Attributes\Route;
use Core\Routing\Attributes\RouteGroup;
use Modules\Cms\Domain\Services\BlockCacheManager;
use Modules\Cms\Infrastructure\Models\Page;

/**
 * Cache Management Controller
 *
 * Admin interface for managing block caches
 */
#[RouteGroup(prefix: '/admin/cache', middleware: ['auth', 'admin'])]
class CacheManagementController
{
    public function __construct(
        private readonly BlockCacheManager $cacheManager,
    ) {
    }

    /**
     * Show cache management dashboard
     */
    #[Route('/', method: 'GET', name: 'admin.cache.index')]
    public function index(): Response
    {
        $stats = $this->cacheManager->getStats();

        return response(view('cms.admin.cache.index', [
            'stats' => $stats,
        ]));
    }

    /**
     * Clear all block caches
     */
    #[Route('/clear-all', method: 'POST', name: 'admin.cache.clear-all')]
    public function clearAll(): Response
    {
        try {
            $this->cacheManager->clearAll();

            return response()->json([
                'success' => true,
                'message' => 'All block caches cleared successfully',
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cache: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clear cache for specific page
     */
    #[Route('/clear-page/{id}', method: 'POST', name: 'admin.cache.clear-page')]
    public function clearPage(int $id): Response
    {
        try {
            $page = Page::findOrFail($id);
            $this->cacheManager->invalidatePage($page);

            return response()->json([
                'success' => true,
                'message' => "Cache cleared for page: {$page->name}",
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear page cache: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clear cache for specific region
     */
    #[Route('/clear-region', method: 'POST', name: 'admin.cache.clear-region')]
    public function clearRegion(Request $request): Response
    {
        try {
            $region = $request->input('region');
            $pageId = $request->input('page_id');

            if ($pageId) {
                $this->cacheManager->invalidatePageRegion((int)$pageId, $region);
                $message = "Cache cleared for region '{$region}' on page {$pageId}";
            } else {
                $this->cacheManager->invalidateGlobalRegion($region);
                $message = "Cache cleared for global region '{$region}'";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear region cache: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Warm up cache for specific page
     */
    #[Route('/warmup-page/{id}', method: 'POST', name: 'admin.cache.warmup-page')]
    public function warmupPage(int $id): Response
    {
        try {
            $page = Page::findOrFail($id);
            $this->cacheManager->warmUpPage($page);

            return response()->json([
                'success' => true,
                'message' => "Cache warmed up for page: {$page->name}",
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to warm up cache: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Warm up cache for all popular pages
     */
    #[Route('/warmup-all', method: 'POST', name: 'admin.cache.warmup-all')]
    public function warmupAll(): Response
    {
        try {
            $pages = Page::where('slug', 'home')
                ->orWhere('status', 'published')
                ->limit(10)
                ->get();

            $count = 0;
            foreach ($pages as $page) {
                try {
                    $this->cacheManager->warmUpPage($page);
                    $count++;
                } catch (\Throwable $e) {
                    // Log but continue
                    logger()->warning("Failed to warm up page {$page->id}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Cache warmed up for {$count} page(s)",
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to warm up caches: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get cache statistics
     */
    #[Route('/stats', method: 'GET', name: 'admin.cache.stats')]
    public function stats(): Response
    {
        try {
            $stats = $this->cacheManager->getStats();

            return response()->json([
                'success' => true,
                'stats' => $stats,
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get cache stats: ' . $e->getMessage(),
            ], 500);
        }
    }
}
