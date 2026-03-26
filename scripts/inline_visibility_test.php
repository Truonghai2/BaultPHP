<?php
/**
 * Inline test isVisibleTo logic
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

use Modules\Cms\Infrastructure\Models\PageBlock;

$block = PageBlock::find(9);

echo "\n=== INLINE VISIBILITY TEST ===\n\n";

echo "Block attributes:\n";
echo "  visible: " . var_export($block->visible, true) . "\n";
echo "  allowed_roles: " . var_export($block->allowed_roles, true) . "\n";
echo "  visibility_rules: " . var_export($block->visibility_rules, true) . "\n\n";

echo "Step-by-step logic:\n";

// Step 1
if (!$block->visible) {
    echo "  Step 1: !visible = TRUE → return FALSE\n";
    exit;
} else {
    echo "  Step 1: !visible = FALSE → continue\n";
}

// Step 2
echo "  Step 2: checking allowed_roles...\n";
echo "    empty(allowed_roles) = " . var_export(empty($block->allowed_roles), true) . "\n";
echo "    is_array(allowed_roles) = " . var_export(is_array($block->allowed_roles), true) . "\n";

if (!empty($block->allowed_roles) && is_array($block->allowed_roles)) {
    echo "    → Entering allowed_roles check\n";
    echo "    → Would check user roles here\n";
} else {
    echo "    → SKIPPING allowed_roles (empty or not array)\n";
}

// Step 3
echo "  Step 3: checking visibility_rules...\n";
echo "    empty(visibility_rules) = " . var_export(empty($block->visibility_rules), true) . "\n";
echo "    is_array(visibility_rules) = " . var_export(is_array($block->visibility_rules), true) . "\n";

if (!empty($block->visibility_rules) && is_array($block->visibility_rules)) {
    echo "    → Would call evaluateVisibilityRules()\n";
} else {
    echo "    → SKIPPING visibility_rules (empty or not array)\n";
}

echo "\n  Step 4: Should return TRUE\n";

echo "\n=== ACTUAL METHOD RESULT ===\n";
$result = $block->isVisibleTo(null);
echo "Result: " . var_export($result, true) . "\n\n";

if ($result) {
    echo "✅ VISIBLE!\n";
} else {
    echo "❌ NOT VISIBLE - LOGIC BUG!\n";
}

echo "\n";
