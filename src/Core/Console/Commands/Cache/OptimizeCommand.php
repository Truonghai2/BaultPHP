<?php

namespace Core\Console\Commands\Cache;

use Core\Console\Contracts\BaseCommand;
use Symfony\Component\Process\Process;

/**
 * A command to run all caching operations for the framework.
 * This provides a single entry point for optimizing the application for production.
 */
class OptimizeCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'optimize';
    }

    public function description(): string
    {
        return 'Cache the framework bootstrap files (config, routes, events, etc.) for a performance boost.';
    }

    public function handle(): int
    {
        $this->comment('Optimizing the application...');
        $this->line('');

        $this->call('config:cache');
        $this->call('route:cache');
        $this->call('event:cache');
        $this->call('view:cache');
        $this->call('command:cache');
        $this->call('bootstrap:cache');

        $this->comment('Dumping optimized class-map...');
        $this->dumpAutoload();

        $this->call('optimize:compile');

        $this->line('');
        $this->info('✔ Application optimized successfully!');

        return self::SUCCESS;
    }

    /**
     * Run the Composer dump-autoload command with optimization flags.
     */
    protected function dumpAutoload(): void
    {
        $process = new Process(['composer', 'dump-autoload', '--optimize', '--no-dev'], base_path());
        $process->mustRun(function ($type, $buffer) {
            $this->io->write($buffer);
        });
    }
}
