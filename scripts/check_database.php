<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

use Modules\Cms\Infrastructure\Models\Page;
use Modules\Cms\Infrastructure\Models\PageBlock;
use Modules\Cms\Infrastructure\Models\BlockType;

echo "===============================================\n";
echo "DATABASE CHECK - Pages and Blocks\n";
echo "===============================================\n\n";

try {
    // Check Pages
    echo "1. CHECKING PAGES:\n";
    echo "-------------------\n";
    $pages = Page::all();
    echo "Total pages: " . $pages->count() . "\n\n";
    
    foreach($pages as $page) {
        echo "Page ID: {$page->id}\n";
        echo "  - Slug: {$page->slug}\n";
        echo "  - Title: {$page->title}\n";
        echo "  - Status: {$page->status}\n";
        echo "  - Visible: " . ($page->visible ? 'Yes' : 'No') . "\n";
        echo "  - Template: {$page->template}\n\n";
    }
    
    // Check Block Types
    echo "\n2. CHECKING BLOCK TYPES:\n";
    echo "------------------------\n";
    $blockTypes = BlockType::all();
    echo "Total block types: " . $blockTypes->count() . "\n\n";
    
    foreach($blockTypes as $type) {
        echo "Block Type ID: {$type->id}\n";
        echo "  - Name: {$type->name}\n";
        echo "  - Class: {$type->class}\n";
        echo "  - Active: " . ($type->active ? 'Yes' : 'No') . "\n\n";
    }
    
    // Check Page Blocks
    echo "\n3. CHECKING PAGE BLOCKS:\n";
    echo "------------------------\n";
    $pageBlocks = PageBlock::all();
    echo "Total page blocks: " . $pageBlocks->count() . "\n\n";
    
    foreach($pageBlocks as $block) {
        echo "Block ID: {$block->id}\n";
        echo "  - Page ID: {$block->page_id}\n";
        echo "  - Block Type ID: {$block->block_type_id}\n";
        echo "  - Region: {$block->region}\n";
        echo "  - Sort Order: {$block->sort_order}\n";
        echo "  - Visible: " . ($block->visible ? 'Yes' : 'No') . "\n";
        echo "  - Content Length: " . strlen($block->content ?? '') . " chars\n\n";
    }
    
    // Check home page specifically
    echo "\n4. DETAILED HOME PAGE CHECK:\n";
    echo "-----------------------------\n";
    $homePage = Page::where('slug', 'home')->first();
    
    if (!$homePage) {
        echo "❌ HOME PAGE NOT FOUND!\n";
        echo "Available slugs: " . $pages->pluck('slug')->implode(', ') . "\n";
    } else {
        echo "✅ Home page found!\n";
        echo "  - ID: {$homePage->id}\n";
        echo "  - Status: {$homePage->status}\n";
        echo "  - Visible: " . ($homePage->visible ? 'Yes' : 'No') . "\n";
        
        $homeBlocks = PageBlock::where('page_id', $homePage->id)->get();
        echo "  - Total blocks: " . $homeBlocks->count() . "\n";
        
        if ($homeBlocks->count() > 0) {
            echo "\n  Blocks breakdown by region:\n";
            $byRegion = $homeBlocks->groupBy('region');
            foreach($byRegion as $region => $blocks) {
                echo "    - {$region}: " . count($blocks) . " blocks\n";
            }
        } else {
            echo "\n  ❌ NO BLOCKS FOUND FOR HOME PAGE!\n";
        }
    }
    
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n===============================================\n";
echo "CHECK COMPLETE\n";
echo "===============================================\n";
