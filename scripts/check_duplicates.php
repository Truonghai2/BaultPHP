<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

use Modules\Cms\Infrastructure\Models\Page;
use Modules\Cms\Infrastructure\Models\BlockInstance;
use Modules\Cms\Infrastructure\Models\PageBlock;

echo "--- CHECKING FOR DUPLICATES ---\n";

// 1. Homepage Blocks
$homePage = Page::where('slug', 'home')->first();
if ($homePage) {
    echo "Homepage ID: {$homePage->id}\n";
    $heroBlocks = PageBlock::where('page_id', $homePage->id)->where('region', 'hero')->get();
    echo "Hero Blocks: {$heroBlocks->count()}\n";
    foreach ($heroBlocks as $b) {
        echo " - ID: {$b->id}, Type: {$b->block_type_id}, Order: {$b->sort_order}\n";
    }
    
    $contentBlocks = PageBlock::where('page_id', $homePage->id)->where('region', 'content')->get();
    echo "Content Blocks: {$contentBlocks->count()}\n";
    foreach ($contentBlocks as $b) {
        echo " - ID: {$b->id}, Type: {$b->block_type_id}, Order: {$b->sort_order}, Name: " . substr($b->blockType->name ?? '?', 0, 20) . "\n";
    }
} else {
    echo "Homepage not found.\n";
}

// 2. Global Header Blocks
echo "\n--- HEADER BLOCKS ---\n";
$headerBlocks = BlockInstance::whereHas('region', function($q) {
    $q->where('name', 'header-nav');
})->get();
echo "Header Nav Blocks: {$headerBlocks->count()}\n";
foreach ($headerBlocks as $b) {
    echo " - ID: {$b->id}, Type: {$b->blockType->name}, Config Keys: " . implode(',', array_keys($b->config ?? [])) . "\n";
    if (isset($b->config['menu_items'])) {
        echo "   - Menu Items Count: " . count($b->config['menu_items']) . "\n";
    } else {
        echo "   - ⚠️ Missing menu_items in config!\n";
    }
}

// 3. Global Content Blocks
echo "\n--- GLOBAL CONTENT BLOCKS ---\n";
$globalContent = BlockInstance::whereHas('region', function($q) {
    $q->where('name', 'content');
})->get();
echo "Global Content Blocks: {$globalContent->count()}\n";
foreach ($globalContent as $b) {
    echo " - ID: {$b->id}, Type: {$b->blockType->name}\n";
}
