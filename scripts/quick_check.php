<?php
/**
 * Quick Check - Page Block System
 * Simple script to verify page block system status
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

use Modules\Cms\Infrastructure\Models\Page;
use Modules\Cms\Infrastructure\Models\PageBlock;
use Modules\Cms\Infrastructure\Models\BlockType;

echo "\n";
echo "╔════════════════════════════════════════════╗\n";
echo "║   PAGE BLOCK SYSTEM - QUICK CHECK         ║\n";
echo "╚════════════════════════════════════════════╝\n\n";

// Helper function for status
function status($condition, $message) {
    echo ($condition ? "✅ " : "❌ ") . $message . "\n";
    return $condition;
}

try {
    // 1. Check Models Exist
    echo "1. MODELS CHECK:\n";
    echo "   ---------------\n";
    $modelsOk = true;
    $modelsOk &= status(class_exists('Modules\\Cms\\Infrastructure\\Models\\Page'), "Page model exists");
    $modelsOk &= status(class_exists('Modules\\Cms\\Infrastructure\\Models\\PageBlock'), "PageBlock model exists");
    $modelsOk &= status(class_exists('Modules\\Cms\\Infrastructure\\Models\\BlockType'), "BlockType model exists");
    echo "\n";

    // 2. Check Services Exist
    echo "2. SERVICES CHECK:\n";
    echo "   ----------------\n";
    $servicesOk = true;
    $servicesOk &= status(class_exists('Modules\\Cms\\Domain\\Services\\PageBlockRenderer'), "PageBlockRenderer service exists");
    $servicesOk &= status(function_exists('render_page_blocks'), "render_page_blocks() helper exists");
    echo "\n";

    // 3. Check Database Tables
    echo "3. DATABASE TABLES:\n";
    echo "   -----------------\n";
    
    
    $pagesCount = Page::count();
    $blocksCount = PageBlock::count();
    $typesCount = BlockType::count();
    
    status($pagesCount >= 0, "pages table accessible ({$pagesCount} rows)");
    status($blocksCount >= 0, "page_blocks table accessible ({$blocksCount} rows)");
    status($typesCount >= 0, "block_types table accessible ({$typesCount} rows)");
    echo "\n";

    // 4. Check Home Page
    echo "4. HOME PAGE CHECK:\n";
    echo "   -----------------\n";
    $homePage = Page::where('slug', 'home')->first();
    
    if (status($homePage !== null, "Home page exists")) {
        status($homePage->status === 'published', "Status: {$homePage->status}");
        status($homePage->visible === true, "Visible: " . ($homePage->visible ? 'true' : 'false'));
        
        $homeBlocks = PageBlock::where('page_id', $homePage->id)->count();
        status($homeBlocks > 0, "Has {$homeBlocks} blocks");
    } else {
        echo "   Available pages: " . Page::pluck('slug')->implode(', ') . "\n";
    }
    echo "\n";

    // 5. Check Templates
    echo "5. TEMPLATES CHECK:\n";
    echo "   -----------------\n";
    $templatesOk = true;
    $templatesOk &= status(file_exists(__DIR__ . '/../resources/views/welcome.blade.php'), "welcome.blade.php exists");
    $templatesOk &= status(file_exists(__DIR__ . '/../resources/views/layouts/page.blade.php'), "layouts/page.blade.php exists");
    
    // Check if welcome.blade.php uses render_page_blocks
    $welcomeContent = file_get_contents(__DIR__ . '/../resources/views/welcome.blade.php');
    status(strpos($welcomeContent, 'render_page_blocks') !== false, "welcome.blade.php uses render_page_blocks()");
    echo "\n";

    // 6. Summary
    echo "6. SUMMARY:\n";
    echo "   ---------\n";
    
    $allOk = $modelsOk && $servicesOk && $pagesCount > 0 && $blocksCount > 0 && $typesCount > 0;
    
    if ($allOk) {
        echo "   ✅ Page Block System is FULLY OPERATIONAL\n";
    } else {
        echo "   ⚠️  Page Block System has ISSUES:\n";
        if ($pagesCount === 0) echo "      - No pages in database\n";
        if ($blocksCount === 0) echo "      - No page blocks in database\n";
        if ($typesCount === 0) echo "      - No block types in database\n";
        if (!$homePage) echo "      - Home page not found\n";
    }
    echo "\n";

    // 7. Recommendations
    if (!$allOk) {
        echo "7. RECOMMENDATIONS:\n";
        echo "   -----------------\n";
        if ($typesCount === 0) {
            echo "   → Run: php artisan cms:sync-blocks\n";
        }
        if ($pagesCount === 0) {
            echo "   → Create pages in database\n";
        }
        if ($blocksCount === 0 && $pagesCount > 0) {
            echo "   → Add blocks to pages using add_page_block() helper\n";
        }
        echo "\n";
    }

} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "╔════════════════════════════════════════════╗\n";
echo "║   CHECK COMPLETE                          ║\n";
echo "╚════════════════════════════════════════════╝\n\n";
