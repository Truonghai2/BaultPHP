<?php
/**
 * Check database blocks for home page
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

use Modules\Cms\Infrastructure\Models\Page;
use Modules\Cms\Infrastructure\Models\PageBlock;

echo "\n";
echo "╔════════════════════════════════════════════╗\n";
echo "║   HOME PAGE BLOCKS CHECK                  ║\n";
echo "╚════════════════════════════════════════════╝\n\n";

$homePage = Page::where('slug', 'home')->first();

if (!$homePage) {
    echo "❌ Home page not found!\n\n";
    exit(1);
}

echo "✅ Home page found (ID: {$homePage->id})\n";
echo "   - Title: {$homePage->name}\n";
echo "   - Slug: {$homePage->slug}\n";
echo "   - Status: {$homePage->status}\n";
echo "   - Visible: " . ($homePage->visible ? 'true' : 'false') . "\n\n";

$blocks = PageBlock::where('page_id', $homePage->id)
    ->with('blockType')
    ->orderBy('region')
    ->orderBy('sort_order')
    ->get();

echo "Total blocks: " . $blocks->count() . "\n\n";

if ($blocks->isEmpty()) {
    echo "❌ No blocks found for home page!\n\n";
    exit(1);
}

echo "BLOCKS BY REGION:\n";
echo "-----------------\n";

$byRegion = $blocks->groupBy('region');

foreach ($byRegion as $region => $regionBlocks) {
    echo "\nRegion: '{$region}' ({$regionBlocks->count()} blocks)\n";
    
    foreach ($regionBlocks as $block) {
        $typeName = $block->blockType?->name ?? 'Unknown';
        $typeClass = $block->blockType?->class ?? 'N/A';
        
        echo "  [{$block->sort_order}] {$typeName}\n";
        echo "      - Block ID: {$block->id}\n";
        echo "      - Type ID: {$block->block_type_id}\n";
        echo "      - Class: {$typeClass}\n";
        echo "      - Visible: " . ($block->visible ? 'true' : 'false') . "\n";
        echo "      - Content: " . (strlen($block->content ?? '') > 0 ? strlen($block->content) . ' chars' : 'empty') . "\n";
    }
}

echo "\n";
echo "╔════════════════════════════════════════════╗\n";
echo "║   EXPECTED BY TEMPLATE                    ║\n";
echo "╚════════════════════════════════════════════╝\n\n";

echo "welcome.blade.php expects blocks in these regions:\n";
echo "  - 'hero' region (for hero section)\n";
echo "  - 'content' region (for main content)\n";
echo "  - 'sidebar' region (for sidebar)\n\n";

$hasHero = $byRegion->has('hero');
$hasContent = $byRegion->has('content');
$hasSidebar = $byRegion->has('sidebar');

echo "REGION CHECK:\n";
echo "  - hero: " . ($hasHero ? "✅ Found ({$byRegion['hero']->count()} blocks)" : "❌ Not found") . "\n";
echo "  - content: " . ($hasContent ? "✅ Found ({$byRegion['content']->count()} blocks)" : "❌ Not found") . "\n";
echo "  - sidebar: " . ($hasSidebar ? "✅ Found ({$byRegion['sidebar']->count()} blocks)" : "❌ Not found") . "\n\n";

if (!$hasHero && !$hasContent && !$hasSidebar) {
    echo "⚠️  WARNING: No blocks in expected regions!\n";
    echo "   This will cause the homepage to appear blank.\n\n";
}

echo "╚════════════════════════════════════════════╝\n\n";
