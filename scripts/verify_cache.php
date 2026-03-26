<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

use Core\Cache\CacheManager;

echo "--- VERIFYING CACHE FIX ---\n";

try {
    $cache = app('cache'); // Gets CacheManager
    echo "Cache Driver: " . get_class($cache->store()) . "\n";
    
    // Determine the concrete class of the store implementation wrapped by Repository
    $repo = $cache->store();
    $reflection = new ReflectionClass($repo);
    $property = $reflection->getProperty('store');
    $property->setAccessible(true);
    $storeImpl = $property->getValue($repo);
    echo "Underlying Store: " . get_class($storeImpl) . "\n";

    echo "Testing forgetPattern('test:*')...\n";
    $result = $cache->forgetPattern('test:*');
    
    echo "✅ Success! Result: " . ($result ? 'true' : 'false') . "\n";
} catch (\Throwable $e) {
    echo "❌ FAILED: " . $e->getMessage() . "\n"; // " . $e->getTraceAsString() . "\n";
    exit(1);
}
