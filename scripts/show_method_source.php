<?php
/**
 * Print actual source code of isVisibleTo
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

use Modules\Cms\Infrastructure\Models\PageBlock;

$block = PageBlock::find(9);

$reflection = new ReflectionMethod($block, 'isVisibleTo');

echo "\n=== METHOD LOCATION ===\n";
echo "File: " . $reflection->getFileName() . "\n";
echo "Line: " . $reflection->getStartLine() . " - " . $reflection->getEndLine() . "\n";
echo "Declaring class: " . $reflection->getDeclaringClass()->getName() . "\n\n";

echo "=== SOURCE CODE ===\n";
$file = file($reflection->getFileName());
$start = $reflection->getStartLine() - 1;
$end = $reflection->getEndLine();
$length = $end - $start;

for ($i = 0; $i < $length; $i++) {
    printf("%4d: %s", $start + $i + 1, $file[$start + $i]);
}

echo "\n";
