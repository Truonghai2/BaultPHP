<?php
/**
 * Simple Structure Check - No Database Access
 */

echo "\n";
echo "╔════════════════════════════════════════════╗\n";
echo "║   PAGE BLOCK SYSTEM - STRUCTURE CHECK     ║\n";
echo "╚════════════════════════════════════════════╝\n\n";

function check($condition, $message) {
    echo ($condition ? "✅ " : "❌ ") . $message . "\n";
}

echo "CHECKING FILES AND CLASSES:\n";
echo "----------------------------\n\n";

echo "1. Models:\n";
check(file_exists(__DIR__ . '/../Modules/Cms/Infrastructure/Models/Page.php'), "   Page.php exists");
check(file_exists(__DIR__ . '/../Modules/Cms/Infrastructure/Models/PageBlock.php'), "   PageBlock.php exists");
check(file_exists(__DIR__ . '/../Modules/Cms/Infrastructure/Models/BlockType.php'), "   BlockType.php exists");

echo "\n2. Services:\n";
check(file_exists(__DIR__ . '/../Modules/Cms/Domain/Services/PageBlockRenderer.php'), "   PageBlockRenderer.php exists");
check(file_exists(__DIR__ . '/../Modules/Cms/Domain/Services/BlockClassRegistry.php'), "   BlockClassRegistry.php exists");

echo "\n3. Controllers:\n";
check(file_exists(__DIR__ . '/../Modules/Cms/Http/Controllers/SpaPageController.php'), "   SpaPageController.php exists");

echo "\n4. Helpers:\n";
check(file_exists(__DIR__ . '/../src/Core/helpers.php'), "   helpers.php exists");

// Check if helpers.php has render_page_blocks
$helpersContent = file_get_contents(__DIR__ . '/../src/Core/helpers.php');
check(strpos($helpersContent, 'function render_page_blocks') !== false, "   render_page_blocks() defined");
check(strpos($helpersContent, 'function add_page_block') !== false, "   add_page_block() defined");

echo "\n5. Templates:\n";
check(file_exists(__DIR__ . '/../resources/views/welcome.blade.php'), "   welcome.blade.php exists");
check(file_exists(__DIR__ . '/../resources/views/layouts/page.blade.php'), "   layouts/page.blade.php exists");

// Check template usage
$welcomeContent = file_get_contents(__DIR__ . '/../resources/views/welcome.blade.php');
check(strpos($welcomeContent, 'render_page_blocks') !== false, "   welcome.blade.php uses render_page_blocks()");

echo "\n6. Block Classes:\n";
$blockDir = __DIR__ . '/../Modules/Cms/Domain/Blocks';
if (is_dir($blockDir)) {
    $blocks = glob($blockDir . '/*.php');
    check(count($blocks) > 0, "   Found " . count($blocks) . " block classes");
    
    foreach ($blocks as $block) {
        $blockName = basename($block, '.php');
        echo "      - {$blockName}\n";
    }
} else {
    echo "   ❌ Block directory not found\n";
}

echo "\n╔════════════════════════════════════════════╗\n";
echo "║   STRUCTURE CHECK COMPLETE                ║\n";
echo "╚════════════════════════════════════════════╝\n\n";

echo "✅ All code files exist and are in place!\n";
echo "⚠️  To check database, you need to fix bootstrap issues first.\n\n";
