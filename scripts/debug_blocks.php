<?php
/**
 * Debug blocks - show ALL data
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

use Modules\Cms\Infrastructure\Models\Page;
use Modules\Cms\Infrastructure\Models\PageBlock;

echo "\n";
echo "╔════════════════════════════════════════════╗\n";
echo "║   DEBUG: RAW BLOCK DATA                   ║\n";
echo "╚════════════════════════════════════════════╝\n\n";

$homePage = Page::where('slug', 'home')->first();

if (!$homePage) {
    echo "❌ Home page not found!\n";
    exit(1);
}

echo "Home page ID: {$homePage->id}\n\n";

$blocks = PageBlock::where('page_id', $homePage->id)->get();

echo "Total blocks: " . $blocks->count() . "\n\n";

foreach ($blocks as $block) {
    echo "Block ID: {$block->id}\n";
    echo "  - page_id: " . var_export($block->page_id, true) . "\n";
    echo "  - block_type_id: " . var_export($block->block_type_id, true) . "\n";
    echo "  - region: '" . ($block->region ?? 'NULL') . "' (length: " . strlen($block->region ?? '') . ")\n";
    echo "  - region raw: " . var_export($block->region, true) . "\n";
    echo "  - sort_order: " . var_export($block->sort_order, true) . "\n";
    echo "  - visible: " . var_export($block->visible, true) . "\n";
    echo "  - content: " . (empty($block->content) ? 'empty' : strlen($block->content) . ' chars') . "\n";
    
    // Try to get raw attributes
    $attributes = $block->getAttributes();
    echo "  - Raw attributes:\n";
    foreach ($attributes as $key => $value) {
        if (in_array($key, ['page_id', 'block_type_id', 'region', 'sort_order', 'visible'])) {
            echo "      {$key} = " . var_export($value, true) . "\n";
        }
    }
    echo "\n";
}

echo "╚════════════════════════════════════════════╝\n\n";
