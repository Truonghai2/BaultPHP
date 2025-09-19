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

if (!in_array(PHP_SAPI, ['cli', 'phpdbg', 'micro'], true)) {
    // Prevent this script from being executed in a web context.
    return;
}

echo "[Preloading] Starting BaultPHP preloading...\n";

$preloadCount = 0;
$preloadStartTime = microtime(true);

// Directories to preload. Adjust this list as needed for your application.
$paths = [
    __DIR__ . '/src',
    __DIR__ . '/config',
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

echo sprintf("[Preloading] Preloaded %d files in %.2fms\n", $preloadCount, (microtime(true) - $preloadStartTime) * 1000);
