<?php

namespace Core\Console\Commands\Projections;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Core\Events\ProjectionRunner;

/**
 * Build Projections Command.
 * 
 * Rebuilds all projections from scratch.
 * 
 * Usage: php artisan projections:build
 */
class ProjectionsBuildCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'projections:build 
                {--projection= : Specific projection to rebuild}
                {--reset : Reset before building}';
    }

    public function description(): string
    {
        return 'Build read model projections from event stream';
    }

    public function handle(): int
    {
        $this->info('Building projections...');

        $runner = $this->app->make(ProjectionRunner::class);

        $specific = $this->option('projection');

        if ($specific) {
            $this->comment("Building projection: {$specific}");
            $runner->rebuild($specific);
        } else {
            $this->comment("Building all projections");
            $runner->buildAll();
        }

        $this->info('✅ Projections built successfully!');

        return 0;
    }
}
