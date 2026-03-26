<?php

namespace Core\Console\Commands\Cache;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Core\Performance\JITOptimizer;

/**
 * Generate OPcache Preload File
 * 
 * Creates a preload.php file for OPcache to preload frequently used classes.
 */
class OptimizePreloadCommand extends BaseCommand
{
    public function __construct(
        Application $app,
        protected ?JITOptimizer $optimizer = null
    ) {
        parent::__construct($app);
        $this->optimizer = $optimizer ?? $app->make(JITOptimizer::class);
    }

    public function signature(): string
    {
        return 'optimize:preload
                {--output= : Output file path (default: bootstrap/cache/preload.php)}
                {--limit=500 : Maximum number of files to preload}
                {--min-hits=10 : Minimum access count for a file to be included}';
    }

    public function description(): string
    {
        return 'Generate OPcache preload file for better performance';
    }

    public function handle(): int
    {
        if (!function_exists('opcache_compile_file')) {
            $this->error('❌ OPcache preloading requires PHP 7.4+ with OPcache enabled');
            return self::FAILURE;
        }

        $outputPath = $this->option('output') ?: base_path('bootstrap/cache/preload.php');
        $limit = (int) $this->option('limit');
        $minHits = (int) $this->option('min-hits');

        $this->info('🚀 Generating OPcache preload file...');
        $this->line('');

        try {
            // Get files to preload
            $files = $this->getFilesToPreload($limit, $minHits);

            if (empty($files)) {
                $this->warn('No files found to preload. Run the application to collect profiling data.');
                $this->comment('Tip: Access your application pages to generate profiling data, then run this command again.');
                return self::SUCCESS;
            }

            // Generate preload file
            $this->generatePreloadFile($outputPath, $files);

            $this->line('');
            $this->info('✔ Preload file generated successfully!');
            $this->line('');
            $this->io->table(
                ['Metric', 'Value'],
                [
                    ['Output File', $outputPath],
                    ['Files Included', count($files)],
                    ['File Size', $this->formatBytes(filesize($outputPath))],
                ]
            );

            $this->line('');
            $this->displayUsageInstructions($outputPath);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('❌ Failed to generate preload file: ' . $e->getMessage());
            if ($this->io->isVerbose()) {
                $this->line($e->getTraceAsString());
            }
            return self::FAILURE;
        }
    }

    /**
     * Get files to preload based on profiling data.
     */
    protected function getFilesToPreload(int $limit, int $minHits): array
    {
        $stats = $this->optimizer->getStats();
        
        // Get profiling data
        $cachePath = storage_path('framework/cache/opcache_profiles.php');
        
        if (!file_exists($cachePath)) {
            // Generate default list of important framework files
            return $this->getDefaultFiles($limit);
        }

        $profiles = require $cachePath;

        // Filter by minimum hits
        $files = array_filter($profiles, fn($profile) => ($profile['access_count'] ?? 0) >= $minHits);

        // Sort by access count (descending)
        uasort($files, fn($a, $b) => ($b['access_count'] ?? 0) <=> ($a['access_count'] ?? 0));

        // Limit number of files
        $files = array_slice(array_keys($files), 0, $limit);

        // Filter out non-existent files
        $files = array_filter($files, fn($file) => file_exists($file));

        // If still empty, use default files
        if (empty($files)) {
            return $this->getDefaultFiles($limit);
        }

        return array_values($files);
    }

    /**
     * Get default framework files to preload.
     */
    protected function getDefaultFiles(int $limit): array
    {
        $basePath = base_path();
        $files = [];

        // Core framework files
        $corePatterns = [
            'src/Core/**/*.php',
            'src/Http/**/*.php',
            'src/Providers/**/*.php',
            'vendor/symfony/*/**.php',
            'vendor/psr/*/**.php',
        ];

        foreach ($corePatterns as $pattern) {
            $matches = glob($basePath . '/' . str_replace('**', '*', $pattern), GLOB_BRACE);
            if ($matches) {
                $files = array_merge($files, $matches);
            }
        }

        // Remove duplicates and non-existent files
        $files = array_unique($files);
        $files = array_filter($files, fn($file) => file_exists($file) && is_file($file));

        // Limit number of files
        return array_slice(array_values($files), 0, $limit);
    }

    /**
     * Generate the preload file.
     */
    protected function generatePreloadFile(string $outputPath, array $files): void
    {
        $outputDir = dirname($outputPath);
        
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0775, true);
        }

        $content = $this->generatePreloadContent($files);
        
        file_put_contents($outputPath, $content);
        
        // Make it executable
        chmod($outputPath, 0644);
    }

    /**
     * Generate preload file content.
     */
    protected function generatePreloadContent(array $files): string
    {
        $basePath = base_path();
        $timestamp = date('Y-m-d H:i:s');
        $count = count($files);

        $content = <<<PHP
<?php
/**
 * OPcache Preload File
 * 
 * Generated: {$timestamp}
 * Files: {$count}
 * 
 * This file is automatically generated by the optimize:preload command.
 * It preloads frequently used PHP files into OPcache for better performance.
 * 
 * To use this file, add the following to your php.ini:
 * opcache.preload={$basePath}/bootstrap/cache/preload.php
 * opcache.preload_user=www-data
 */

// Check if OPcache is available
if (!function_exists('opcache_compile_file')) {
    return;
}

// Track statistics
\$preloaded = 0;
\$failed = 0;
\$startTime = microtime(true);

// Preload files
\$files = [

PHP;

        // Add file paths
        foreach ($files as $file) {
            // Make path relative to base path for portability
            $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $file);
            $relativePath = str_replace('\\', '/', $relativePath);
            $content .= "    __DIR__ . '/../../{$relativePath}',\n";
        }

        $content .= <<<'PHP'
];

foreach ($files as $file) {
    try {
        if (file_exists($file)) {
            opcache_compile_file($file);
            $preloaded++;
        } else {
            $failed++;
        }
    } catch (Throwable $e) {
        $failed++;
        // Silently fail - preload should not break the application
    }
}

$duration = round((microtime(true) - $startTime) * 1000, 2);

// Log preload statistics (optional)
if (defined('STDERR')) {
    fwrite(STDERR, sprintf(
        "[OPcache Preload] Preloaded %d files in %.2fms (%d failed)\n",
        $preloaded,
        $duration,
        $failed
    ));
}

PHP;

        return $content;
    }

    /**
     * Display usage instructions.
     */
    protected function displayUsageInstructions(string $outputPath): void
    {
        $this->comment('📚 Usage Instructions:');
        $this->line('');
        $this->line('1. Add the following lines to your php.ini:');
        $this->line('');
        $this->line("   opcache.preload={$outputPath}");
        $this->line('   opcache.preload_user=www-data');
        $this->line('');
        $this->line('2. Restart your PHP-FPM or web server:');
        $this->line('');
        $this->line('   sudo systemctl restart php-fpm');
        $this->line('   # or');
        $this->line('   sudo systemctl restart php8.2-fpm');
        $this->line('');
        $this->line('3. Verify preloading is working:');
        $this->line('');
        $this->line('   php -r "var_dump(opcache_get_status()[\'preload_statistics\']);"');
        $this->line('');
        $this->comment('⚠️  Important Notes:');
        $this->line('');
        $this->line('  • Preloading requires PHP 7.4+ with OPcache enabled');
        $this->line('  • The preload user must have read access to all files');
        $this->line('  • Preloading happens once at server startup');
        $this->line('  • Changes to preloaded files require server restart');
        $this->line('  • Monitor memory usage - preloading increases memory consumption');
        $this->line('');
        $this->comment('💡 Tips:');
        $this->line('');
        $this->line('  • Run this command after major code changes');
        $this->line('  • Use --limit option to control number of preloaded files');
        $this->line('  • Use --min-hits option to filter frequently used files');
        $this->line('  • Check "php bault optimize:jit stats" for OPcache statistics');
    }

    /**
     * Format bytes to human readable format.
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
