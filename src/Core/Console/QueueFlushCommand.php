<?php

namespace Core\Console;

use Core\Console\Contracts\BaseCommand;

class QueueFlushCommand extends BaseCommand
{
    protected string $signature = 'queue:flush';
    protected string $description = 'Flush all of the failed queue jobs.';

    public function signature(): string
    {
        return $this->signature;
    }
    
    public function description(): string
    {
        return $this->description;
    }
    
    public function handle(array $args = []): void
    {
        $logPath = storage_path('logs/failed_jobs.log');

        if (!file_exists($logPath) || filesize($logPath) === 0) {
            $this->io->info('No failed jobs to flush.');
            return;
        }

        if ($this->io->confirm('Are you sure you want to flush all failed jobs? This cannot be undone.', false)) {
            unlink($logPath);
            $this->io->success('All failed jobs have been flushed.');
        } else {
            $this->io->info('Operation cancelled.');
        }
    }
}