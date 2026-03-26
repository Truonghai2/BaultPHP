<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

use Modules\Cms\Infrastructure\Models\Page;
use Modules\Cms\Infrastructure\Models\BlockInstance;
use Modules\Cms\Infrastructure\Models\PageBlock;

$homePage = Page::where('slug', 'home')->first();

echo "--- DEBUG LAYOUT RENDERING ---\n";

// 1. Check Header Region
echo "\n[1] Checking 'header-nav' region:\n";
$headerNav = render_block_region('header-nav');
echo "Length: " . strlen($headerNav) . "\n";
echo "Content Preview: " . substr(strip_tags($headerNav), 0, 100) . "...\n";
if (empty($headerNav)) {
    echo "⚠️  header-nav is EMPTY. Fallback should trigger.\n";
} else {
    echo "✅ header-nav has content. Fallback should be HIDDEN.\n";
}

// Check if block instance exists
$navBlock = BlockInstance::whereHas('region', function($q) { $q->where('name', 'header-nav'); })->first();
if ($navBlock) {
    echo "Block Instance Found: ID {$navBlock->id}\n";
    echo "Config: " . json_encode($navBlock->config) . "\n";
    echo "Visible: " . ($navBlock->visible ? 'YES' : 'NO') . "\n";
    echo "Allowed Roles: " . json_encode($navBlock->allowed_roles) . "\n";
} else {
    echo "❌ No BlockInstance found in header-nav!\n";
}


// 2. Check Homepage Hero
echo "\n[2] Checking Homepage Hero:\n";
$pageBlocks = PageBlock::where('page_id', $homePage->id)->where('region', 'hero')->get();
echo "Page Blocks in Page 'hero' region: {$pageBlocks->count()}\n";
foreach ($pageBlocks as $pb) {
    echo " - Block ID: {$pb->id}, Type: {$pb->block_type_id}, Visible: {$pb->visible}\n";
}

$heroContent = render_page_blocks($homePage, 'hero');
echo "Page Block Render Length: " . strlen($heroContent) . "\n";

$staticHero = render_block_region('homepage-hero');
echo "Static Region 'homepage-hero' Length: " . strlen($staticHero) . "\n";

if (!empty($heroContent) && !empty($staticHero)) {
    echo "⚠️  POTENTIAL DUPLICATION: Both Page Blocks and Static Region have content!\n";
    echo "If welcome.blade.php logic is flawed, both might show.\n";
} else {
    echo "✅ No obvious overlap detected via simple count.\n";
}

// 3. Check Homepage Content
echo "\n[3] Checking Homepage Content:\n";
$contentBlocks = render_page_blocks($homePage, 'content');
echo "Page 'content' Render Length: " . strlen($contentBlocks) . "\n";

$globalContent = render_block_region('content');
echo "Global 'content' Region Render Length: " . strlen($globalContent) . "\n";

if (!empty($contentBlocks) && !empty($globalContent)) {
    echo "⚠️  POTENTIAL DUPLICATION: Page has content blocks AND Global content blocks exist!\n";
    echo "Layout app.blade.php renders global 'content' (line 70) AND welcome.blade.php renders page 'content' (line 35).\n";
    echo "THIS IS LIKELY THE CAUSE OF DUPLICATE HOME INTERFACE.\n";
}
