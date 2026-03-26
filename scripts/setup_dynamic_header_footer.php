<?php
/**
 * Setup Dynamic Header and Footer Blocks
 * 
 * This script creates global BlockInstances for:
 * 1. Header Navigation (header-nav)
 * 2. Header User Menu (header-user)
 * 3. Footer (footer)
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

use Modules\Cms\Infrastructure\Models\BlockInstance;
use Modules\Cms\Infrastructure\Models\BlockType;
use Modules\Cms\Infrastructure\Models\BlockRegion;

echo "\n╔════════════════════════════════════════════╗\n";
echo "║   SETUP DYNAMIC HEADER & FOOTER BLOCKS    ║\n";
echo "╚════════════════════════════════════════════╝\n\n";

// 1. Ensure Regions Exist
$regions = ['header-nav', 'header-user', 'footer'];
foreach ($regions as $name) {
    if (!BlockRegion::where('name', $name)->exists()) {
        BlockRegion::create(['name' => $name, 'description' => ucfirst(str_replace('-', ' ', $name)) . ' Region']);
        echo "✅ Created region: {$name}\n";
    } else {
        echo "ℹ️  Region exists: {$name}\n";
    }
}

// 2. Setup Header Navigation
echo "\n--- Setting up Header Navigation ---\n";
$navBlockType = BlockType::where('name', 'navigation')->first();
if ($navBlockType) {
    // Config matching the static header links
    $navConfig = [
        'menu_items' => [
            [
                'label' => 'Home',
                'url' => '/',
                'target' => '_self',
            ],
            [
                'label' => 'About',
                'url' => '/about',
                'target' => '_self',
            ],
            [
                'label' => 'Contact',
                'url' => '/contact',
                'target' => '_self',
            ],
        ],
        'style' => 'horizontal',
    ];

    $navRegion = BlockRegion::where('name', 'header-nav')->first();
    
    // Check if instance exists
    $instance = BlockInstance::where('region_id', $navRegion->id)
        ->where('block_type_id', $navBlockType->id)
        ->first();

    if ($instance) {
        $instance->config = $navConfig;
        $instance->sort_order = 0;
        $instance->visible = true;
        $instance->save();
        echo "✅ Updated existing navigation block in 'header-nav'\n";
    } else {
        BlockInstance::create([
            'block_type_id' => $navBlockType->id,
            'region_id' => $navRegion->id,
            'name' => 'Main Navigation',
            'config' => $navConfig,
            'sort_order' => 0,
            'visible' => true,
        ]);
        echo "✅ Created new navigation block in 'header-nav'\n";
    }
} else {
    echo "❌ Error: 'navigation' block type not found!\n";
}

// 3. Setup User Menu
echo "\n--- Setting up User Menu ---\n";
$userMenuBlockType = BlockType::where('name', 'user-menu')->first();
if ($userMenuBlockType) {
    $userMenuRegion = BlockRegion::where('name', 'header-user')->first();
    
    // Check if instance exists
    $instance = BlockInstance::where('region_id', $userMenuRegion->id)
        ->where('block_type_id', $userMenuBlockType->id)
        ->first();

    if ($instance) {
        $instance->visible = true;
        $instance->save();
        echo "✅ Updated existing user-menu block in 'header-user'\n";
    } else {
        BlockInstance::create([
            'block_type_id' => $userMenuBlockType->id,
            'region_id' => $userMenuRegion->id,
            'name' => 'User Menu',
            'config' => [],
            'sort_order' => 0,
            'visible' => true,
        ]);
        echo "✅ Created new user-menu block in 'header-user'\n";
    }
} else {
    echo "❌ Error: 'user-menu' block type not found!\n";
}

// 4. Setup Footer
echo "\n--- Setting up Footer ---\n";
$footerBlockType = BlockType::where('name', 'footer')->first();
if ($footerBlockType) {
    // Config matching the static footer content
    $footerConfig = [
        'columns' => [
            [
                'title' => 'Product',
                'links' => [
                    ['label' => 'Features', 'url' => '/features'],
                    ['label' => 'Pricing', 'url' => '/pricing'],
                    ['label' => 'Documentation', 'url' => '/docs'],
                ],
            ],
            [
                'title' => 'Company',
                'links' => [
                    ['label' => 'About', 'url' => '/about'],
                    ['label' => 'Blog', 'url' => '/blog'],
                    ['label' => 'Contact', 'url' => '/contact'],
                ],
            ],
            [
                'title' => 'Resources',
                'links' => [
                    ['label' => 'Guides', 'url' => '/guides'],
                    ['label' => 'API Reference', 'url' => '/api'],
                    ['label' => 'Support', 'url' => '/support'],
                ],
            ],
            [
                'title' => 'Legal',
                'links' => [
                    ['label' => 'Privacy', 'url' => '/privacy'],
                    ['label' => 'Terms', 'url' => '/terms'],
                    ['label' => 'License', 'url' => '/license'],
                ],
            ],
        ],
        'copyright' => '© ' . date('Y') . ' BaultPHP Framework. All rights reserved.',
        'social_links' => [
            ['platform' => 'GitHub', 'url' => 'https://github.com/Truonghai2/BaultPHP', 'icon' => '📦'],
            ['platform' => 'Twitter', 'url' => 'https://twitter.com/baultphp', 'icon' => '🐦'],
        ],
    ];

    $footerRegion = BlockRegion::where('name', 'footer')->first();
    
    // Check if instance exists - remove any existing to ensure clean slate or update? 
    // Let's update or create specific one.
    $instance = BlockInstance::where('region_id', $footerRegion->id)
        ->where('block_type_id', $footerBlockType->id)
        ->first();

    if ($instance) {
        $instance->config = $footerConfig;
        $instance->visible = true;
        $instance->save();
        echo "✅ Updated existing footer block in 'footer'\n";
    } else {
        BlockInstance::create([
            'block_type_id' => $footerBlockType->id,
            'region_id' => $footerRegion->id,
            'name' => 'Main Footer',
            'config' => $footerConfig,
            'sort_order' => 0,
            'visible' => true,
        ]);
        echo "✅ Created new footer block in 'footer'\n";
    }

} else {
    echo "❌ Error: 'footer' block type not found!\n";
}

// 5. Clear Cache
echo "\n--- Clearing Cache ---\n";
try {
    $cacheManager = app(\Modules\Cms\Domain\Services\BlockCacheManager::class);
    $cacheManager->clearAll();
    echo "✅ Block cache cleared\n";
} catch (\Exception $e) {
    echo "⚠️  Could not clear cache via manager: " . $e->getMessage() . "\n";
    // Fallback
    cache()->flush();
    echo "✅ System cache flushed\n";
}

echo "\n✨ COMPLETE! Dynamic blocks setup finished.\n";
