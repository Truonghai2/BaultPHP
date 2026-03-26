<?php
/**
 * Detailed Block Render Test with Error Capture
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

use Modules\Cms\Infrastructure\Models\Page;
use Modules\Cms\Infrastructure\Models\PageBlock;
use Modules\Cms\Infrastructure\Models\BlockType;

echo "\n╔════════════════════════════════════════════╗\n";
echo "║   DETAILED RENDER TEST (DEBUG MODE)       ║\n";
echo "╚════════════════════════════════════════════╝\n\n";

// 1. Check if view() function exists
echo "1. Checking view() function:\n";
if (function_exists('view')) {
    echo "   ✅ view() function EXISTS\n";
    
    try {
        $viewFactory = view();
        echo "   ✅ view() returns: " . get_class($viewFactory) . "\n";
    } catch (\Exception $e) {
        echo "   ❌ view() ERROR: " . $e->getMessage() . "\n";
    }
} else {
    echo "   ❌ view() function DOES NOT EXIST\n";
}

// 2. Check view namespace
echo "\n2. Checking view namespace registration:\n";
try {
    $viewFactory = view();
    $cmsViewPath = __DIR__ . '/../Modules/Cms/resources/views';
    echo "   - CMS views path: {$cmsViewPath}\n";
    echo "   - Path exists: " . (is_dir($cmsViewPath) ? 'Yes' : 'No') . "\n";
    
    // Try to check if view exists
    if (method_exists($viewFactory, 'exists')) {
        $heroViewExists = $viewFactory->exists('cms::blocks.homepage-hero');
        echo "   - cms::blocks.homepage-hero exists: " . ($heroViewExists ? 'Yes' : 'No') . "\n";
    }
} catch (\Exception $e) {
    echo "   ❌ ERROR: " . $e->getMessage() . "\n";
}

// 3. Test render a single block manually
echo "\n3. Testing manual block render:\n";
try {
    $homePage = Page::where('slug', 'home')->first();
    if (!$homePage) {
        echo "   ❌ Home page not found\n";
        exit(1);
    }
    
    $heroBlock = PageBlock::where('page_id', $homePage->id)
        ->where('region', 'hero')
        ->with('blockType')
        ->first();
    
    if (!$heroBlock) {
        echo "   ❌ Hero block not found\n";
    } else {
        echo "   ✅ Found hero block (ID: {$heroBlock->id})\n";
        echo "   - Block type: {$heroBlock->blockType->name}\n";
        echo "   - Block class: {$heroBlock->blockType->class}\n";
        
        // Try to instantiate block class
        $blockClass = $heroBlock->blockType->class;
        echo "\n   Testing block instantiation:\n";
        
        if (!class_exists($blockClass)) {
            echo "   ❌ Block class does not exist: {$blockClass}\n";
        } else {
            echo "   ✅ Block class exists\n";
            
            try {
                $blockInstance = new $blockClass();
                echo "   ✅ Block instantiated successfully\n";
                
                // Try to get default config
                echo "\n   Getting default config:\n";
                $defaultConfig = $blockInstance->getDefaultConfig();
                echo "   - Config keys: " . implode(', ', array_keys($defaultConfig)) . "\n";
                
                // Try to render
                echo "\n   Attempting to render:\n";
                try {
                    $html = $blockInstance->render($defaultConfig);
                    $htmlLength = strlen($html);
                    echo "   - Rendered HTML length: {$htmlLength} chars\n";
                    
                    if ($htmlLength > 0) {
                        echo "   ✅ SUCCESS! Block rendered HTML\n";
                        echo "\n   First 200 chars of HTML:\n";
                        echo "   " . substr($html, 0, 200) . "...\n";
                    } else {
                        echo "   ❌ EMPTY HTML returned\n";
                    }
                } catch (\Exception $e) {
                    echo "   ❌ Render EXCEPTION: " . $e->getMessage() . "\n";
                    echo "   Stack trace:\n";
                    echo "   " . $e->getTraceAsString() . "\n";
                }
                
            } catch (\Exception $e) {
                echo "   ❌ Instantiation failed: " . $e->getMessage() . "\n";
            }
        }
    }
    
} catch (\Exception $e) {
    echo "   ❌ ERROR: " . $e->getMessage() . "\n";
    echo "   Stack: " . $e->getTraceAsString() . "\n";
}

// 4. Test view rendering directly
echo "\n4. Testing direct view rendering:\n";
try {
    $viewPath = 'cms::blocks.homepage-hero';
    $testData = [
        'logo_url' => '/test.png',
        'show_badge' => true,
        'badge_text' => 'Test',
        'badge_link_text' => 'v1.0',
        'badge_link_url' => '#',
        'title' => 'Test Title',
        'description' => 'Test Description',
        'primary_button_text' => 'Button',
        'primary_button_url' => '#',
        'secondary_button_text' => 'Link',
        'secondary_button_url' => '#',
        'show_code_preview' => false,
    ];
    
    echo "   Trying to render: {$viewPath}\n";
    $html = view($viewPath, $testData)->render();
    $htmlLength = strlen($html);
    echo "   - Rendered length: {$htmlLength} chars\n";
    
    if ($htmlLength > 0) {
        echo "   ✅ View renders successfully!\n";
        echo "\n   First 200 chars:\n";
        echo "   " . substr($html, 0, 200) . "...\n";
    } else {
        echo "   ❌ View returned empty\n";
    }
    
} catch (\Exception $e) {
    echo "   ❌ View render FAILED: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    echo "\n   Full trace:\n";
    echo "   " . $e->getTraceAsString() . "\n";
}

echo "\n╚════════════════════════════════════════════╝\n\n";
