<?php
/**
 * Test PageBlockRenderer directly
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

use Modules\Cms\Infrastructure\Models\Page;
use Modules\Cms\Domain\Services\PageBlockRenderer;

echo "\n╔════════════════════════════════════════════╗\n";
echo "║   TESTING PageBlockRenderer DIRECTLY      ║\n";
echo "╚════════════════════════════════════════════╝\n\n";

$page = Page::find(1);

if (!$page) {
    echo "❌ Page not found\n";
    exit(1);
}

echo "✅ Found page: {$page->title}\n\n";

// Get renderer
$renderer = app(PageBlockRenderer::class);

// Disable cache
$renderer->withoutCache();

echo "Testing renderPageBlocks():\n";
echo str_repeat('-', 50) . "\n";

try {
    $html = $renderer->renderPageBlocks($page, 'hero');
    echo "Hero region result: " . strlen($html) . " chars\n";
    
    if (strlen($html) > 0) {
        echo "✅ SUCCESS!\n";
        echo "First 200 chars:\n";
        echo substr($html, 0, 200) . "\n";
    } else {
        echo "❌ EMPTY result from renderPageBlocks\n";
        
        // Debug: Check what blocksInRegion returns
        echo "\nDEBUG: Checking blocksInRegion()...\n";
        $blocks = $page->blocksInRegion('hero');
        echo "Blocks count: " . $blocks->count() . "\n";
        
        foreach ($blocks as $block) {
            echo "\nBlock #{$block->id}:\n";
            echo "  - Type ID: {$block->block_type_id}\n";
            echo "  - Region: {$block->region}\n";
            echo "  - Visible: " . ($block->visible ? 'yes' : 'no') . "\n";
            echo "  - Has blockType: " . ($block->blockType ? 'yes' : 'no') . "\n";
            
            if ($block->blockType) {
                echo "  - Block type name: {$block->blockType->name}\n";
                echo "  - Block type class: {$block->blockType->class}\n";
                echo "  - Block type active: " . ($block->blockType->active ? 'yes' : 'no') . "\n";
                
                // Try render directly
                try {
                    $blockHtml = $block->renderOptimized();
                    echo "  - Direct render: " . strlen($blockHtml) . " chars\n";
                    
                    if (empty($blockHtml)) {
                        echo "  ⚠️  renderOptimized() returned EMPTY\n";
                    }
                } catch (\Exception $e) {
                    echo "  ❌ Render error: {$e->getMessage()}\n";
                }
            }
        }
    }
} catch (\Exception $e) {
    echo "❌ ERROR: {$e->getMessage()}\n";
    echo "File: {$e->getFile()}:{$e->getLine()}\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n╚════════════════════════════════════════════╝\n\n";
