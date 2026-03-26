<?php
/**
 * Fix home page visibility and regions
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

use Modules\Cms\Infrastructure\Models\Page;
use Modules\Cms\Infrastructure\Models\PageBlock;
use Modules\Cms\Infrastructure\Models\BlockType;

echo "\n";
echo "╔════════════════════════════════════════════╗\n";
echo "║   FIXING HOME PAGE                        ║\n";
echo "╚════════════════════════════════════════════╝\n\n";

// 1. Fix home page visibility
$homePage = Page::where('slug', 'home')->first();

if (!$homePage) {
    echo "❌ Home page not found!\n";
    exit(1);
}

echo "Step 1: Checking home page status...\n";
echo "  - Status: {$homePage->status}\n";
$publishedAt = $homePage->published_at ? $homePage->published_at->format('Y-m-d H:i:s') : 'Not set';
echo "  - Published at: {$publishedAt}\n";


// Ensure page is published
if ($homePage->status !== 'published') {
    echo "\nStep 1.1: Setting status to 'published'...\n";
    $homePage->status = 'published';
    $homePage->published_at = date('Y-m-d H:i:s');
    $homePage->save();
    echo "✅ Home page is now published\n";
} else {
    echo "✅ Home page is already published\n";
}
echo "\n";


// 2. Delete old blocks
echo "Step 2: Deleting old blocks...\n";
$deleted = PageBlock::where('page_id', $homePage->id)->delete();
echo "✅ Deleted {$deleted} old blocks\n\n";

// 3. Create new blocks with correct regions
echo "Step 3: Creating blocks in correct regions...\n";

$blocksToCreate = [
    [
        'block_name' => 'homepage-hero',
        'region' => 'hero',
        'order' => 0,
    ],
    [
        'block_name' => 'homepage-features',
        'region' => 'content',
        'order' => 0,
    ],
    [
        'block_name' => 'homepage-stats',
        'region' => 'content',
        'order' => 1,
    ],
];

foreach ($blocksToCreate as $blockData) {
    $blockType = BlockType::where('name', $blockData['block_name'])->first();
    
    if (!$blockType) {
        echo "  ❌ Block type '{$blockData['block_name']}' not found\n";
        continue;
    }
    
    $block = PageBlock::create([
        'page_id' => $homePage->id,
        'block_type_id' => $blockType->id,
        'region' => $blockData['region'],
        'sort_order' => $blockData['order'],
        'visible' => true,
        'created_by' => 1,
    ]);
    
    echo "  ✅ Created '{$blockData['block_name']}' in '{$blockData['region']}' region (ID: {$block->id})\n";
}

echo "\n╔════════════════════════════════════════════╗\n";
echo "║   FIX COMPLETED                           ║\n";
echo "╚════════════════════════════════════════════╝\n\n";

echo "Now run: docker compose exec app php scripts/check_home_blocks.php\n";
echo "to verify the fix!\n\n";
