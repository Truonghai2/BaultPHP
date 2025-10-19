<?php

/**
 * OPcache Preloading script for BaultPHP.
 *
 * This script is executed once when the server starts. It compiles and caches
 * the most frequently used framework and application files into OPcache's memory,
 * significantly improving performance by eliminating file I/O and script
 * interpretation on every request.
 *
 * @see https://www.php.net/manual/en/opcache.preloading.php
 */

// Chỉ thực hiện preloading trong môi trường production.
// Trong môi trường dev, preloading sẽ ngăn hot-reload hoạt động đúng cách.
if (($_ENV['APP_ENV'] ?? 'production') !== 'production') {
    return;
}

// Set environment variables as early as possible to prevent deprecated shutdown handlers.
putenv('REVOLT_DRIVER_DISABLE_SHUTDOWN_HANDLER=1');
putenv('AMPHP_PROCESS_DISABLE_SHUTDOWN_HANDLER=1');
putenv('AMPHP_HTTP_CLIENT_DISABLE_SHUTDOWN_HANDLER=1');

if (!in_array(PHP_SAPI, ['cli', 'phpdbg', 'micro'], true)) {
    // Prevent this script from being executed in a web context.
    return;
}

$preloadStartTime = microtime(true);
$preloadCount = 0;

// Directories to preload. Adjust this list as needed for your application.
$paths = [
    __DIR__ . '/src',
    __DIR__ . '/config',
    __DIR__ . '/database/seeders',
];

foreach ($paths as $path) {
    if (!is_dir($path)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY,
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php' && !str_contains($file->getPathname(), '/vendor/phpunit/')) {
            opcache_compile_file($file->getPathname());
            $preloadCount++;
        }
    }
}

$duration = (microtime(true) - $preloadStartTime) * 1000;

echo sprintf("[Preloading] Preloaded %d files in %.2fms\n", $preloadCount, $duration);
