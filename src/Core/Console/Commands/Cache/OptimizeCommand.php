<?php

namespace Core\Console\Commands\Cache;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Symfony\Component\Process\Process;

/**
 * A command to run all caching operations for the framework.
 * This provides a single entry point for optimizing the application for production.
 */
class OptimizeCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'optimize
                {--skip-blocks : Skip block cache optimization}
                {--skip-acl : Skip ACL cache optimization}
                {--skip-jit : Skip JIT optimization}
                {--skip-warmup : Skip cache warming}
                {--no-autoload : Skip composer autoload optimization}';
    }

    public function description(): string
    {
        return 'Cache the framework bootstrap files and optimize the application for production.';
    }

    public function handle(): int
    {
        $this->comment('🚀 Optimizing the application...');
        $this->line('');

        // Step 1: Cache framework files
        $this->info('Step 1: Caching framework files...');
        $this->call('config:cache');
        $this->call('route:cache');
        $this->call('event:cache');
        $this->call('view:cache');
        $this->call('command:cache');
        $this->call('bootstrap:cache');
        if ($this->app->bound(\Core\Module\LazyModuleLoader::class)) {
            $this->app->make(\Core\Module\LazyModuleLoader::class)->warmCache();
        }

        // Step 2: Cache blocks (CMS)
        if (!$this->option('skip-blocks')) {
            $this->line('');
            $this->info('Step 2: Caching blocks...');
            try {
                $this->call('cache:blocks');
            } catch (\Throwable $e) {
                $this->warn('Block cache skipped: ' . $e->getMessage());
            }
        } else {
            $this->comment('Skipping block cache optimization');
        }

        // Step 3: Optimize autoloader
        if (!$this->option('no-autoload')) {
            $this->line('');
            $this->info('Step 3: Optimizing autoloader...');
            $this->dumpAutoload();
        } else {
            $this->comment('Skipping autoloader optimization');
        }

        // Step 4: Compile service container
        $this->line('');
        $this->info('Step 4: Compiling service container...');
        $this->call('optimize:compile');

        // Step 5: JIT Optimization
        if (!$this->option('skip-jit')) {
            $this->line('');
            $this->info('Step 5: JIT optimization...');
            try {
                $this->call('optimize:jit');
            } catch (\Throwable $e) {
                $this->warn('JIT optimization skipped: ' . $e->getMessage());
            }
        } else {
            $this->comment('Skipping JIT optimization');
        }

        // Step 6: ACL Optimization
        if (!$this->option('skip-acl')) {
            $this->line('');
            $this->info('Step 6: Optimizing ACL cache...');
            try {
                $this->call('acl:optimize', ['action' => 'warm']);
            } catch (\Throwable $e) {
                $this->warn('ACL optimization skipped: ' . $e->getMessage());
            }
        } else {
            $this->comment('Skipping ACL optimization');
        }

        // Step 7: Warm up caches
        if (!$this->option('skip-warmup')) {
            $this->line('');
            $this->info('Step 7: Warming up caches...');
            try {
                $this->call('cache:warmup', ['--popular' => true]);
            } catch (\Throwable $e) {
                $this->warn('Cache warmup skipped: ' . $e->getMessage());
            }
        } else {
            $this->comment('Skipping cache warmup');
        }

        $this->line('');
        $this->info('✔ Application optimized successfully!');
        $this->line('');
        $this->comment('💡 Tip: Run "php bault performance:report" to verify optimization results');

        return self::SUCCESS;
    }

    /**
     * Run the Composer dump-autoload command with optimization flags.
     * Timeout 300s to avoid failure on slow I/O (e.g. Docker volume mount).
     */
    protected function dumpAutoload(): void
    {
        $process = new Process(['composer', 'dump-autoload', '--optimize', '--no-dev'], base_path());
        $process->setTimeout(300);
        $process->mustRun(function ($type, $buffer) {
            $this->io->write($buffer);
        });
    }
}
