<?php

namespace Core\Console\Commands;

use Core\Console\Contracts\BaseCommand;

class QueueFlushCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'queue:flush';
    }
    
    public function description(): string
    {
        return 'Flush all of the failed queue jobs.';
    }
    
    public function handle(): int
    {
        $logPath = storage_path('logs/failed_jobs.log');

        if (!file_exists($logPath) || filesize($logPath) === 0) {
            $this->info('No failed jobs to flush.');
            return 0;
        }

        if ($this->io->confirm('Are you sure you want to flush all failed jobs? This cannot be undone.', false)) {
            unlink($logPath);
            $this->info('✔ All failed jobs have been flushed.');
            return 0;
        } else {
            $this->comment('Operation cancelled.');
            return 1;
        }
    }
}