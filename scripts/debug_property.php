<?php
/**
 * Test property values INSIDE the method
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

use Modules\Cms\Infrastructure\Models\PageBlock;

// Extend PageBlock to access internals
class DebugPageBlock extends PageBlock
{
    public function debugIsVisibleTo($user = null): array
    {
        $debug = [];
        
        $debug['this->visible (direct)'] = $this->visible;
        $debug['this->visible (type)'] = gettype($this->visible);
        $debug['!this->visible'] = !$this->visible;
        $debug['this->visible === true'] = $this->visible === true;
        $debug['this->visible == true'] = $this->visible == true;
        $debug['this->visible == 1'] = $this->visible == 1;
        
        // Try getAttribute
        if (method_exists($this, 'getAttribute')) {
            $debug['getAttribute(visible)'] = $this->getAttribute('visible');
        }
        
        // Check attributes array
        if (method_exists($this, 'getAttributes')) {
            $attrs = $this->getAttributes();
            $debug['attributes[visible]'] = $attrs['visible'] ?? 'NOT SET';
        }
        
        return $debug;
    }
}

$block = DebugPageBlock::find(9);

echo "\n=== PROPERTY DEBUG ===\n\n";

$debug = $block->debugIsVisibleTo();

foreach ($debug as $key => $value) {
    echo sprintf("%-30s = %s\n", $key, var_export($value, true));
}

echo "\n=== ACTUAL CALL ===\n";
echo "isVisibleTo(null) = " . var_export($block->isVisibleTo(null), true) . "\n\n";
