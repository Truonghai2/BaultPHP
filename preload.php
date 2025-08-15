<?php

/**
 * OPcache Preloading Script
 *
 * This script is executed once when the server starts. It compiles and caches
 * the most frequently used framework and application files into OPcache's memory,
 * significantly boosting performance by eliminating disk I/O for these files
 * on every request.
 *
 * @see https://www.php.net/manual/en/opcache.preloading.php
 */

if (in_array(PHP_SAPI, ['cli', 'phpdbg'], true)) {
    return;
}

echo "[Preloading] Starting OPcache preloading...\n";

// A list of files and directories to preload.
$paths = [
    __DIR__ . '/vendor/autoload.php', // Always preload the autoloader
    __DIR__ . '/bootstrap',
    __DIR__ . '/src',
    __DIR__ . '/Modules',
    __DIR__ . '/config',
    // Add specific, high-traffic vendor packages for maximum performance
    __DIR__ . '/vendor/symfony',
    __DIR__ . '/vendor/monolog/monolog',
    __DIR__ . '/vendor/illuminate',
    __DIR__ . '/vendor/psr',
];

// Files to explicitly exclude (e.g., tests, docs, etc.)
$excludePatterns = [
    '/tests/',
    '/Test/',
    '/Documentation/',
    '/\.md$/',
    '/phpunit/',
];

$files = [];

foreach ($paths as $path) {
    if (is_dir($path)) {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $filePath = $file->getRealPath();
                $shouldExclude = false;
                foreach ($excludePatterns as $pattern) {
                    if (preg_match($pattern, str_replace('\\', '/', $filePath))) {
                        $shouldExclude = true;
                        break;
                    }
                }
                if (!$shouldExclude) {
                    $files[] = $filePath;
                }
            }
        }
    } elseif (is_file($path)) {
        $files[] = $path;
    }
}

$files = array_unique($files);
$count = 0;
foreach ($files as $file) {
    try {
        opcache_compile_file($file);
        $count++;
    } catch (\Throwable $e) {
        // It's often safe to ignore errors for files that cannot be compiled
        // (e.g., files with syntax errors or non-PHP content).
    }
}

echo "[Preloading] Preloaded {$count} files into OPcache.\n";
