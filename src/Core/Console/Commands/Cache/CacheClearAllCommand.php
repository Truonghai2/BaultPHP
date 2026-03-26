<?php

namespace Core\Console\Commands\Cache;

use Core\Application;
use Core\Console\Contracts\BaseCommand;

/**
 * Clear all caches in the application.
 * This provides a safe way to clear all caches at once.
 */
class CacheClearAllCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'cache:clear-all
                {--force : Force clear without confirmation}';
    }

    public function description(): string
    {
        return 'Clear all application caches (config, routes, views, compiled, blocks, etc.)';
    }

    public function handle(): int
    {
        if (!$this->option('force')) {
            if (!$this->confirm('This will clear ALL caches. Continue?', true)) {
                $this->comment('Operation cancelled.');
                return self::SUCCESS;
            }
        }

        $this->comment('🧹 Clearing all caches...');
        $this->line('');

        $cleared = [];
        $failed = [];

        // Clear individual caches
        $caches = [
            'config:clear' => 'Configuration cache',
            'route:clear' => 'Route cache',
            'event:clear' => 'Event cache',
            'view:clear' => 'View cache',
            'command:clear' => 'Command cache',
            'bootstrap:clear' => 'Bootstrap cache',
            'optimize:clear' => 'Compiled container',
        ];

        foreach ($caches as $command => $name) {
            try {
                $this->call($command);
                $cleared[] = $name;
            } catch (\Throwable $e) {
                $failed[] = "$name: {$e->getMessage()}";
            }
        }

        // Clear block cache (CMS module)
        try {
            $this->call('cache:clear-blocks');
            $cleared[] = 'Block cache';
        } catch (\Throwable $e) {
            // Block cache might not be available
            $this->comment('Block cache not available (skipped)');
        }

        // Clear application cache
        try {
            $cache = $this->app->make('cache');
            $cache->flush();
            $cleared[] = 'Application cache';
        } catch (\Throwable $e) {
            $failed[] = "Application cache: {$e->getMessage()}";
        }

        // Clear OPcache if available
        if (function_exists('opcache_reset')) {
            if (opcache_reset()) {
                $cleared[] = 'OPcache';
            } else {
                $failed[] = 'OPcache: Reset failed';
            }
        }

        // Clear compiled views directory
        $this->clearDirectory(storage_path('framework/views'));
        $cleared[] = 'Compiled views directory';

        // Clear cache directory
        $this->clearDirectory(storage_path('framework/cache'));
        $cleared[] = 'Cache directory';

        $this->line('');
        
        if (!empty($cleared)) {
            $this->info('✔ Cleared caches:');
            foreach ($cleared as $item) {
                $this->line('  • ' . $item);
            }
        }

        if (!empty($failed)) {
            $this->line('');
            $this->warn('⚠ Failed to clear:');
            foreach ($failed as $item) {
                $this->line('  • ' . $item);
            }
        }

        $this->line('');
        $this->info('✔ All caches cleared successfully!');

        return self::SUCCESS;
    }

    /**
     * Clear a directory of files.
     */
    protected function clearDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $files = glob($path . '/*');
        
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
}
