<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

use Modules\Cms\Infrastructure\Models\BlockInstance;
use Modules\Cms\Infrastructure\Models\BlockType;
use Modules\Cms\Infrastructure\Models\BlockRegion;
use Modules\Cms\Domain\Services\BlockCacheManager;

echo "--- CLEANUP AND FIX SCRIPT ---\n";

// 1. Fix Duplication: Remove GLOBAL blocks from 'content' region
// The homepage content should come from Page Blocks (specific to Home page), not Global Blocks
echo "[1] Checking for stray global blocks in 'content' region...\n";
$contentRegion = BlockRegion::where('name', 'content')->first();
if ($contentRegion) {
    $strayBlocks = BlockInstance::where('region_id', $contentRegion->id)
        ->where('context_type', 'global')
        ->get();
    
    echo "Found {$strayBlocks->count()} global blocks in 'content' region.\n";
    foreach ($strayBlocks as $block) {
        echo " - Deleting block: {$block->name} (Type: {$block->blockType->name})\n";
        $block->delete();
    }
}

// 2. Fix Missing Header: Ensure Header Nav is correct
echo "\n[2] ensuring Header Navigation is correct...\n";
$navRegion = BlockRegion::where('name', 'header-nav')->first();
$navType = BlockType::where('name', 'navigation')->first();

if ($navRegion && $navType) {
    $instance = BlockInstance::where('region_id', $navRegion->id)
        ->where('block_type_id', $navType->id)
        ->first();
        
    if ($instance) {
        echo "Found existing Header Nav block.\n";
        // Ensure config has menu_items
        $config = $instance->config ?? [];
        if (empty($config['menu_items'])) {
            echo "⚠️  Config missing menu_items! Patching...\n";
            $config['menu_items'] = [
                ['label' => 'Home', 'url' => '/', 'target' => '_self'],
                ['label' => 'About', 'url' => '/about', 'target' => '_self'],
                ['label' => 'Contact', 'url' => '/contact', 'target' => '_self'],
            ];
            $instance->config = $config;
            $instance->save();
            echo "✅ Header Nav patched.\n";
        } else {
            echo "✅ Header Nav has menu_items.\n";
        }
    } else {
        echo "⚠️  Header Nav block missing! Creating...\n";
        BlockInstance::create([
            'region_id' => $navRegion->id,
            'block_type_id' => $navType->id,
            'name' => 'Main Navigation',
            'config' => [
                'menu_items' => [
                    ['label' => 'Home', 'url' => '/', 'target' => '_self'],
                    ['label' => 'About', 'url' => '/about', 'target' => '_self'],
                    ['label' => 'Contact', 'url' => '/contact', 'target' => '_self'],
                ],
                'style' => 'horizontal',
            ],
            'sort_order' => 0,
            'weight' => 0,
            'visible' => true,
            'context_type' => 'global', // Explicit global context
            'visibility_mode' => 'always',
            'visibility_rules' => [],
            'allowed_roles' => [],
            'denied_roles' => [],
        ]);
        echo "✅ Created Header Nav block.\n";
    }
}

// 3. Clear Cache
echo "\n[3] Clearing Cache...\n";
try {
    app(BlockCacheManager::class)->clearAll();
    echo "✅ Block cache cleared.\n";
} catch (\Exception $e) {
    cache()->flush();
    echo "✅ System cache flushed.\n";
}

echo "\n✨ DONE.\n";
