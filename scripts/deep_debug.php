<?php
/**
 * Deep debug - why blocks don't render
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

use Modules\Cms\Infrastructure\Models\PageBlock;
use Modules\Cms\Domain\Blocks\HomepageHeroBlock;

echo "\n╔════════════════════════════════════════════╗\n";
echo "║   DEEP DEBUG - WHY NO RENDER?             ║\n";
echo "╚════════════════════════════════════════════╝\n\n";

// Get the hero block with blockType relationship
$block = PageBlock::with('blockType')->find(9);

if (!$block) {
    echo "❌ Block not found\n";
    exit(1);
}

echo "Block #9 details:\n";
echo "  - visible: " . ($block->visible ? 'true' : 'false') . "\n";
echo "  - region: {$block->region}\n";
echo "  - block_type_id: {$block->block_type_id}\n";
echo "  - content: " . (empty($block->content) ? 'empty' : strlen($block->content) . ' chars') . "\n";

// Check visibility
echo "\nTesting isVisibleTo(null):\n";
$isVisible = $block->isVisibleTo(null);
echo "  Result: " . ($isVisible ? 'TRUE' : 'FALSE') . "\n";

if (!$isVisible) {
    echo "  ❌ BLOCK IS NOT VISIBLE!\n";
    echo "  - allowed_roles: " . var_export($block->allowed_roles, true) . "\n";
    echo "  - visibility_rules: " . var_export($block->visibility_rules, true) . "\n";
    exit(1);
}

// Check block type
echo "\nBlock type:\n";
if (!$block->blockType) {
    echo "  ❌ NO BLOCK TYPE!\n";
    exit(1);
}

echo "  - name: {$block->blockType->name}\n";
echo "  - class: {$block->blockType->class}\n";

// Check class
$blockClass = $block->blockType->class;
if (!class_exists($blockClass)) {
    echo "  ❌ CLASS DOESN'T EXIST: {$blockClass}\n";
    exit(1);
}

echo "  ✅ Class exists\n";

// Instantiate
echo "\nInstantiating block class:\n";
try {
    $blockInstance = new $blockClass();
    echo "  ✅ Instantiated\n";
} catch (\Exception $e) {
    echo "  ❌ Error: {$e->getMessage()}\n";
    exit(1);
}

// Get config
echo "\nGetting config:\n";
$config = $block->getConfig();
echo "  Config: " . json_encode(array_keys($config)) . "\n";

// Try render
echo "\nTrying block->render():\n";
try {
    $html = $blockInstance->render($config);
    echo "  - Result length: " . strlen($html) . " chars\n";
    
    if (strlen($html) > 0) {
        echo "  ✅ RENDERS OK!\n";
        echo "\n  First 200 chars:\n";
        echo "  " . substr($html, 0, 200) . "\n";
    } else {
        echo "  ❌ EMPTY HTML from render()\n";
    }
} catch (\Exception $e) {
    echo "  ❌ Render error: {$e->getMessage()}\n";
    echo "  File: {$e->getFile()}:{$e->getLine()}\n";
}

// Try renderOptimized
echo "\nTrying renderOptimized():\n";
try {
    $html = $block->renderOptimized(null, null, null);
    echo "  - Result length: " . strlen($html) . " chars\n";
    
    if (strlen($html) > 0) {
        echo "  ✅ RENDERS OK!\n";
    } else {
        echo "  ❌ EMPTY HTML from renderOptimized()\n";
    }
} catch (\Exception $e) {
    echo "  ❌ Error: {$e->getMessage()}\n";
    echo "  Stack: " . $e->getTraceAsString() . "\n";
}

echo "\n╚════════════════════════════════════════════╝\n\n";
