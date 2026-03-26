<?php

namespace Core\Console\Commands\Cache;

use Core\Application;
use Core\Console\Contracts\BaseCommand;

/**
 * Cache Warmup Command
 * 
 * Master command to warm up all application caches.
 */
class CacheWarmupCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'cache:warmup
                {--all : Warm up all caches (blocks, ACL, etc.)}
                {--blocks : Warm up block cache only}
                {--acl : Warm up ACL cache only}
                {--popular : Warm up popular items only (recommended)}
                {--routes : Preload routes}';
    }

    public function description(): string
    {
        return 'Warm up application caches for better performance';
    }

    public function handle(): int
    {
        $all = $this->option('all');
        $blocks = $this->option('blocks');
        $acl = $this->option('acl');
        $popular = $this->option('popular');
        $routes = $this->option('routes');

        // If no specific option, default to popular
        if (!$all && !$blocks && !$acl && !$routes) {
            $popular = true;
        }

        $this->comment('🔥 Warming up caches...');
        $this->line('');

        $warmed = [];
        $failed = [];
        $skipped = [];

        if ($all || $blocks || $popular) {
            $this->info('Warming up block cache...');
            try {
                $this->call('cache:blocks');
                $warmed[] = 'Block cache';
            } catch (\Throwable $e) {
                if ($this->isCommandNotFound($e)) {
                    $skipped[] = 'Block cache (command not available)';
                } else {
                    $failed[] = "Block cache: {$e->getMessage()}";
                }
            }
            $this->line('');
        }

        // Warm up ACL cache
        if ($all || $acl || $popular) {
            $this->info('Warming up ACL cache...');
            try {
                $this->call('acl:optimize', ['action' => 'warm']);
                $warmed[] = 'ACL cache';
            } catch (\Throwable $e) {
                if ($this->isCommandNotFound($e)) {
                    $skipped[] = 'ACL cache (command not available)';
                } else {
                    $failed[] = "ACL cache: {$e->getMessage()}";
                }
            }
            $this->line('');
        }

        // Warm up routes
        if ($all || $routes) {
            $this->info('Preloading routes...');
            try {
                $this->warmupRoutes();
                $warmed[] = 'Routes';
            } catch (\Throwable $e) {
                $failed[] = "Routes: {$e->getMessage()}";
            }
            $this->line('');
        }

        // Warm up config
        if ($all) {
            $this->info('Preloading configuration...');
            try {
                $this->warmupConfig();
                $warmed[] = 'Configuration';
            } catch (\Throwable $e) {
                $failed[] = "Configuration: {$e->getMessage()}";
            }
            $this->line('');
        }

        // Display results
        $this->displayResults($warmed, $failed, $skipped);

        return empty($failed) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Warm up routes by loading them into memory.
     */
    protected function warmupRoutes(): void
    {
        $router = $this->app->make('router');
        
        // Load all routes
        $routes = $router->getRoutes();
        
        $this->comment("  • Loaded " . count($routes) . " routes into memory");
    }

    /**
     * Warm up configuration by loading all config files.
     */
    protected function warmupConfig(): void
    {
        $configPath = config_path();
        
        if (!is_dir($configPath)) {
            throw new \RuntimeException('Config directory not found');
        }

        $files = glob($configPath . '/*.php');
        $loaded = 0;

        foreach ($files as $file) {
            $key = basename($file, '.php');
            config($key);
            $loaded++;
        }

        $this->comment("  • Loaded {$loaded} configuration files into memory");
    }

    /**
     * Display warmup results.
     */
    protected function displayResults(array $warmed, array $failed, array $skipped): void
    {
        if (!empty($warmed)) {
            $this->info('✔ Warmed up:');
            foreach ($warmed as $item) {
                $this->line('  • ' . $item);
            }
            $this->line('');
        }

        if (!empty($skipped)) {
            $this->comment('⊘ Skipped:');
            foreach ($skipped as $item) {
                $this->line('  • ' . $item);
            }
            $this->line('');
        }

        if (!empty($failed)) {
            $this->warn('⚠ Failed:');
            foreach ($failed as $item) {
                $this->line('  • ' . $item);
            }
            $this->line('');
        }

        if (empty($failed)) {
            $this->info('✔ Cache warmup completed successfully!');
            $this->line('');
            $this->comment('💡 Tip: Run this command after deployment for optimal performance');
        } else {
            $this->warn('⚠ Cache warmup completed with some failures');
        }
    }

    /**
     * Check if exception is due to command not found or namespace not registered.
     */
    protected function isCommandNotFound(\Throwable $e): bool
    {
        $message = $e->getMessage();
        return str_contains($message, 'not found')
            || str_contains($message, 'does not exist')
            || str_contains($message, 'unknown command')
            || (str_contains($message, 'no commands defined') && str_contains($message, 'namespace'));
    }
}
