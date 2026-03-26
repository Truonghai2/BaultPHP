<?php
/**
 * Activate block types for homepage
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

use Modules\Cms\Infrastructure\Models\BlockType;

echo "\n╔════════════════════════════════════════════╗\n";
echo "║   ACTIVATING BLOCK TYPES                  ║\n";
echo "╚════════════════════════════════════════════╝\n\n";

$blockNames = ['homepage-hero', 'homepage-features', 'homepage-stats'];

foreach ($blockNames as $name) {
    $blockType = BlockType::where('name', $name)->first();
    
    if ($blockType) {
        $wasActive = $blockType->active;
        $blockType->active = true;
        $blockType->save();
        
        echo "Block type '{$name}':\n";
        echo "  - ID: {$blockType->id}\n";
        echo "  - Was active: " . ($wasActive ? 'yes' : 'no') . "\n";
        echo "  - Now active: yes\n";
        echo "  ✅ Updated\n\n";
    } else {
        echo "❌ Block type '{$name}' not found\n\n";
    }
}

echo "╚════════════════════════════════════════════╝\n\n";
echo "Now test again: docker compose exec app php scripts/test_renderer.php\n\n";
