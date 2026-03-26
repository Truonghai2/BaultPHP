<?php
/**
 * Check block types for homepage blocks
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

use Modules\Cms\Infrastructure\Models\BlockType;
use Modules\Cms\Infrastructure\Models\PageBlock;

echo "\n╔════════════════════════════════════════════╗\n";
echo "║   BLOCK TYPES CHECK                       ║\n";
echo "╚════════════════════════════════════════════╝\n\n";

$blockTypeIds = [11, 12, 13];

foreach ($blockTypeIds as $typeId) {
    $type = BlockType::find($typeId);
    
    if ($type) {
        echo "BlockType ID {$typeId}:\n";
        echo "  - Name: {$type->name}\n";
        echo "  - Class: {$type->class}\n";
        echo "  - Active: " . ($type->active ? 'Yes' : 'No') . "\n";
        echo "  - Category: {$type->category}\n\n";
    } else {
        echo "❌ BlockType ID {$typeId}: NOT FOUND!\n\n";
    }
}

echo "Now testing render...\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$blocks = PageBlock::where('page_id', 1)
    ->with('blockType')
    ->orderBy('region')
    ->orderBy('sort_order')
    ->get();

foreach ($blocks as $block) {
    echo "Block #{$block->id} ({$block->region}):\n";
    
    if ($block->blockType) {
        echo "  - Type: {$block->blockType->name}\n";
        echo "  - Class: {$block->blockType->class}\n";
        
        // Try to render
        try {
            $html = $block->renderOptimized();
            $preview = strlen($html) > 100 ? substr($html, 0, 100) . '...' : $html;
            echo "  - Rendered: " . strlen($html) . " chars\n";
            if (!empty(trim($html))) {
                echo "  - Preview: " . htmlspecialchars($preview) . "\n";
            } else {
                echo "  - ⚠️  Rendered HTML is EMPTY!\n";
            }
        } catch (\Exception $e) {
            echo "  - ❌ Render error: {$e->getMessage()}\n";
        }
    } else {
        echo "  - ❌ BlockType not found!\n";
    }
    echo "\n";
}

echo "╚════════════════════════════════════════════╝\n\n";
