<?php

/**
 * Debug script for file watcher issues
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Core\Application;
use Core\Server\Development\DockerFileWatcher;

// Bootstrap application
$app = new Application(__DIR__ . '/..');
$app->bootstrap();

echo "=== File Watcher Debug Tool ===\n\n";

// Check environment
echo "Environment Detection:\n";
echo '- Docker Environment: ' . (isDockerEnvironment() ? 'YES' : 'NO') . "\n";
echo '- APP_ENV: ' . config('app.env', 'not set') . "\n";
echo '- APP_DEBUG: ' . (config('app.debug', false) ? 'YES' : 'NO') . "\n\n";

// Check watch configuration
$watchConfig = config('server.swoole.watch', []);
echo "Watch Configuration:\n";
echo '- Directories: ' . json_encode($watchConfig['directories'] ?? []) . "\n";
echo '- Use Polling: ' . ($watchConfig['use_polling'] ?? 'not set') . "\n";
echo '- Interval: ' . ($watchConfig['interval'] ?? 'not set') . "ms\n";
echo '- Ignore Patterns: ' . json_encode($watchConfig['ignore'] ?? []) . "\n\n";

// Check directories
echo "Directory Check:\n";
foreach ($watchConfig['directories'] ?? [] as $dir) {
    $exists = is_dir($dir);
    $readable = $exists ? is_readable($dir) : false;
    echo "- {$dir}: " . ($exists ? 'EXISTS' : 'NOT FOUND') .
         ($readable ? ' (READABLE)' : ' (NOT READABLE)') . "\n";
}
echo "\n";

// Test file scanning
echo "File Scanning Test:\n";
try {
    $testWatcher = new DockerFileWatcher(null, $watchConfig, new \Psr\Log\NullLogger());
    $reflection = new ReflectionClass($testWatcher);
    $method = $reflection->getMethod('scanFiles');
    $method->setAccessible(true);

    $files = $method->invoke($testWatcher);
    echo '- Files found: ' . count($files) . "\n";

    if (count($files) > 0) {
        echo "- Sample files:\n";
        $sampleFiles = array_slice($files, 0, 5, true);
        foreach ($sampleFiles as $file => $mtime) {
            echo "  * {$file} (modified: " . date('Y-m-d H:i:s', $mtime) . ")\n";
        }
    }
} catch (Exception $e) {
    echo '- ERROR: ' . $e->getMessage() . "\n";
}
echo "\n";

// Check log directory
echo "Log Directory Check:\n";
$logDir = storage_path('logs');
echo "- Log directory: {$logDir}\n";
echo '- Exists: ' . (is_dir($logDir) ? 'YES' : 'NO') . "\n";
echo '- Writable: ' . (is_writable($logDir) ? 'YES' : 'NO') . "\n";

$watcherLog = $logDir . '/watcher.log';
echo "- Watcher log: {$watcherLog}\n";
echo '- Exists: ' . (file_exists($watcherLog) ? 'YES' : 'NO') . "\n";
if (file_exists($watcherLog)) {
    echo '- Size: ' . filesize($watcherLog) . " bytes\n";
    echo '- Last modified: ' . date('Y-m-d H:i:s', filemtime($watcherLog)) . "\n";
}
echo "\n";

// Check Swoole process
echo "Swoole Process Check:\n";
$pidFile = storage_path('logs/swoole.pid');
if (file_exists($pidFile)) {
    $pid = trim(file_get_contents($pidFile));
    echo "- PID file exists: {$pid}\n";
    echo '- Process running: ' . (posix_kill($pid, 0) ? 'YES' : 'NO') . "\n";
} else {
    echo "- PID file not found\n";
}
echo "\n";

echo "=== Debug Complete ===\n";
