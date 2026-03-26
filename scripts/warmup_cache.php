<?php

use Core\AppKernel;
use Modules\Cms\Domain\Services\PageBlockRenderer;
use Modules\Cms\Infrastructure\Models\Page;

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = new AppKernel($app);

echo "--- CACHE WARMUP SCRIPT ---\n";
echo "Starting warmup process...\n";

try {
    // 1. Clear existing caches to ensure fresh start
    echo "[1] Clearing Block Caches...\n";
    $cacheManager = app(\Modules\Cms\Domain\Services\BlockCacheManager::class);
    $cacheManager->clearAll();
    echo "    ✅ Cache cleared.\n";

    // 2. Get all published pages
    echo "[2] Fetching Published Pages...\n";
    $pages = Page::where('status', 'published')->get();
    echo "    Found " . $pages->count() . " pages.\n";

    // 3. Warm up each page
    $renderer = app(PageBlockRenderer::class);
    
    foreach ($pages as $page) {
        echo "    🔥 Warming up page: [{$page->id}] {$page->name} (/{$page->slug})...";
        $startTime = microtime(true);
        
        // Warm up cache for guest user (most common case)
        $renderer->warmUpCache($page, null);
        
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        echo " Done ({$duration}ms)\n";
    }
    
    $hasHome = $pages->first(function($page) {
        return $page->slug === 'home';
    }) !== null;

    if (!$hasHome) {
         echo "    Checking for explicit 'home' slug...\n";
         $homePage = Page::where('slug', 'home')->first();
         if ($homePage && $homePage->status !== 'published') {
             echo "    🔥 Warming up offline Home page...\n";
             $renderer->warmUpCache($homePage, null);
             echo "    Done.\n";
         }
    }

    echo "\n✅ WARMUP COMPLETE!\n";

} catch (\Throwable $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
    exit(1);
}
