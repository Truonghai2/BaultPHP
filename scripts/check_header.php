<?php
/**
 * Check header blocks
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

use Modules\Cms\Infrastructure\Models\Page;
use Modules\Cms\Infrastructure\Models\BlockInstance;

echo "\n╔════════════════════════════════════════════╗\n";
echo "║   CHECKING HEADER BLOCKS                  ║\n";
echo "╚════════════════════════════════════════════╝\n\n";

$homePage = Page::where('slug', 'home')->first();

if ($homePage) {
    echo "Home page blocks:\n";
    $regions = ['header', 'hero', 'content', 'sidebar'];
    
    // Get all blocks first
    $allBlocks = $homePage->blocks()->get();
    
    foreach ($regions as $region) {
        $count = $allBlocks->filter(function($block) use ($region) {
            return $block->region === $region;
        })->count();
        echo "  - {$region}: {$count} blocks\n";
    }
}

echo "\nGlobal blocks (BlockInstance):\n";

// Get all global blocks and check manually
$allGlobalBlocks = BlockInstance::all();

$globalRegions = ['header', 'footer'];
foreach ($globalRegions as $region) {
    $regionBlocks = $allGlobalBlocks->filter(function($block) use ($region) {
        return $block->region && $block->region->name === $region;
    });
    
    echo "  - {$region}: {$regionBlocks->count()} blocks\n";
    
    foreach ($regionBlocks as $block) {
        if ($block->blockType) {
            echo "      * {$block->blockType->name}\n";
        }
    }
}

echo "\n╚════════════════════════════════════════════╝\n\n";
