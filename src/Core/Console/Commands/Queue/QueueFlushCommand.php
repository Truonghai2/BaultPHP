<?php

namespace Core\Console\Commands\Queue;

use Core\Console\Contracts\BaseCommand;
use Core\Contracts\Queue\FailedJobProviderInterface;

class QueueFlushCommand extends BaseCommand
{
    public function __construct(protected FailedJobProviderInterface $failedJobProvider)
    {
        parent::__construct();
    }

    public function signature(): string
    {
        return 'queue:flush {--queue= : The name of the queue to flush}';
    }
    public function description(): string
    {
        return 'Flush all or some of the failed queue jobs.';
    }
    public function handle(): int
    {
        $queue = $this->option('queue');

        if ($queue) {
            $count = $this->failedJobProvider->count($queue);

            if ($count === 0) {
                $this->info("No failed jobs found for queue '{$queue}'.");
                return 0;
            }

            if ($this->io->confirm("Are you sure you want to flush {$count} failed job(s) from the '{$queue}' queue?", false)) {
                $deletedCount = $this->failedJobProvider->flush($queue);
                $this->info("✔ {$deletedCount} failed job(s) from the '{$queue}' queue have been flushed.");
            } else {
                $this->comment('Operation cancelled.');
            }
        } else {
            $count = $this->failedJobProvider->count();

            if ($count === 0) {
                $this->info('No failed jobs to flush.');
                return 0;
            }

            if ($this->io->confirm("Are you sure you want to flush all {$count} failed job(s)? This cannot be undone.", false)) {
                $this->failedJobProvider->flush();
                $this->info('✔ All failed jobs have been flushed from the database.');
            } else {
                $this->comment('Operation cancelled.');
            }
        }
        return 0;
    }
}
