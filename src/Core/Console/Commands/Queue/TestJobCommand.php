<?php

namespace Core\Console\Commands\Queue;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Modules\Admin\Application\Jobs\InstallModuleDependenciesJob;

class TestJobCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'queue:test {module? : Module name to test}';
    }

    public function description(): string
    {
        return 'Test if queue system is working by dispatching a test job';
    }

    public function handle(): int
    {
        $moduleName = $this->argument('module') ?? 'Test';

        $this->info('Testing queue system...');
        $this->info('Queue Connection: ' . config('queue.default'));
        $this->line("Dispatching InstallModuleDependenciesJob for module: {$moduleName}");

        try {
            // Use Job's static dispatch method (Dispatchable trait)
            InstallModuleDependenciesJob::dispatch($moduleName);

            $this->info("\n✅ Job dispatched successfully!");

            // Show queue status
            $connection = config('queue.default');
            $this->line("\n📊 Queue Status:");
            $this->line("  Connection: {$connection}");

            if ($connection === 'redis') {
                $this->line('  Check Redis: docker exec bault_redis redis-cli LLEN queues:default');
            } elseif ($connection === 'database') {
                $this->line('  Check DB: SELECT COUNT(*) FROM jobs;');
            }

            $this->line("\n📝 Next steps:");
            $this->line('  1. Run queue worker: php cli queue:work');
            $this->line('  2. Check logs: tail -f storage/logs/app.log');
            $this->line('  3. Check failed_jobs: php cli queue:failed');

            return 0;
        } catch (\Throwable $e) {
            $this->error('Failed to dispatch job: ' . $e->getMessage());
            $this->line($e->getTraceAsString());
            return 1;
        }
    }
}
