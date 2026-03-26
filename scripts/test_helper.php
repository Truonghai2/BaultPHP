<?php
/**
 * Test render_page_blocks() Helper Function
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

use Modules\Cms\Infrastructure\Models\Page;

echo "\n╔════════════════════════════════════════════╗\n";
echo "║   TESTING render_page_blocks() HELPER     ║\n";
echo "╚════════════════════════════════════════════╝\n\n";

$page = Page::where('slug', 'home')->first();

if (!$page) {
    echo "❌ Home page not found!\n";
    exit(1);
}

echo "✅ Found home page (ID: {$page->id})\n\n";

// Test each region
$regions = ['hero', 'content', 'sidebar'];

foreach ($regions as $region) {
    echo "Testing region: '{$region}'\n";
    echo str_repeat('-', 50) . "\n";
    
    try {
        $html = render_page_blocks($page, $region);
        $length = strlen($html);
        
        echo "  - Result length: {$length} chars\n";
        
        if ($length > 0) {
            echo "  ✅ SUCCESS! Rendered HTML\n";
            echo "  - First 150 chars:\n";
            echo "    " . substr($html, 0, 150) . "...\n";
        } else {
            echo "  ❌ EMPTY HTML returned\n";
            
            // Debug: Check if blocks exist for this region
            $blockCount = $page->blocks()->where('region', $region)->count();
            echo "  - Blocks in database for this region: {$blockCount}\n";
            
            if ($blockCount > 0) {
                echo "  ⚠️  PROBLEM: Blocks exist but render returns empty!\n";
            }
        }
    } catch (\Exception $e) {
        echo "  ❌ EXCEPTION: " . $e->getMessage() . "\n";
        echo "  File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
    
    echo "\n";
}

// Test the PageBlockRenderer service directly
echo "\nTesting PageBlockRenderer service directly:\n";
echo str_repeat('=', 50) . "\n";

try {
    $renderer = app('Modules\Cms\Domain\Services\PageBlockRenderer');
    echo "✅ PageBlockRenderer resolved from container\n";
    
    $heroBlocks = $page->blocks()->where('region', 'hero')->get();
    echo "- Found {$heroBlocks->count()} hero blocks\n";
    
    if ($heroBlocks->count() > 0) {
        $heroBlock = $heroBlocks->first();
        echo "\nTrying to render first hero block:\n";
        
        try {
            $html = $heroBlock->renderOptimized();
            echo "  - Rendered: " . strlen($html) . " chars\n";
            
            if (strlen($html) > 0) {
                echo "  ✅ Block renders via renderOptimized()\n";
            } else {
                echo "  ❌ renderOptimized() returns empty\n";
            }
        } catch (\Exception $e) {
            echo "  ❌ renderOptimized() error: " . $e->getMessage() . "\n";
        }
    }
    
} catch (\Exception $e) {
    echo "❌ Service error: " . $e->getMessage() . "\n";
}

echo "\n╚════════════════════════════════════════════╝\n\n";
