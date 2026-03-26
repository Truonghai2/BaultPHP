<?php

namespace Core\Console\Commands\Projections;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Core\Events\ProjectionRunner;

/**
 * Run Projections Command.
 * 
 * Runs projections continuously (daemon mode).
 * 
 * Usage: php artisan projections:run
 */
class ProjectionsRunCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'projections:run 
                {--interval=1000 : Poll interval in milliseconds}';
    }

    public function description(): string
    {
        return 'Run projections continuously (daemon mode)';
    }

    public function handle(): int
    {
        $interval = (int) $this->option('interval');

        $this->info("Starting projection runner (poll interval: {$interval}ms)");
        $this->comment("Press Ctrl+C to stop");

        $runner = $this->app->make(ProjectionRunner::class);
        $runner->runContinuously($interval);

        return 0;
    }
}
