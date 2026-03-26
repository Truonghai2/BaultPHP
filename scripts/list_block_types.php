<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

use Modules\Cms\Infrastructure\Models\BlockType;

echo "Available Block Types:\n";
$types = BlockType::all();
foreach ($types as $t) {
    echo "- [{$t->id}] {$t->name} (Class: {$t->class}, Active: {$t->active})\n";
}
