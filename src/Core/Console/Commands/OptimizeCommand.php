<?php

namespace Core\Console\Commands;

use Core\Console\Contracts\BaseCommand;

class OptimizeCommand extends BaseCommand
{
    /**
     * The signature of the command.
     */
    public function signature(): string
    {
        return 'optimize';
    }

    /**
     * The description of the command.
     */
    public function description(): string
    {
        return 'Cache the framework bootstrap files for better performance.';
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Optimizing the application...');

        $this->comment('› Caching configuration...');
        $this->callCommand(\Core\Console\Commands\Cache\ConfigCacheCommand::class);

        $this->comment('› Caching routes...');
        $this->callCommand(\Core\Console\Commands\Cache\RouteCacheCommand::class);

        $this->comment('› Caching views...');
        $this->callCommand(\Core\Console\Commands\Cache\ViewCacheCommand::class);

        $this->comment('› Caching events...');
        $this->callCommand(\Core\Console\Commands\Cache\EventCacheCommand::class);

        $this->comment('› Caching module providers...');
        $this->callCommand(\Core\Console\Commands\Cache\ProviderCacheCommand::class);

        $this->comment('› Caching enabled modules...');
        $this->callCommand(\Core\Console\Commands\Cache\ModuleCacheCommand::class);

        $this->comment('› Caching bootstrap services...');
        $this->callCommand(\Core\Console\Commands\Cache\BootstrapCacheCommand::class);

        $this->comment('› Compiling service container...');
        $this->callCommand(\Core\Console\Commands\Cache\OptimizeCompileCommand::class);

        $this->info('✔ Application optimized successfully!');

        return self::SUCCESS;
    }
}
