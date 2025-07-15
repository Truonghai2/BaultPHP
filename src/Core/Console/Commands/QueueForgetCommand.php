<?php

namespace Core\Console\Commands;

use Core\Console\Contracts\BaseCommand;

class QueueForgetCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'queue:forget {id : The ID of the failed job to forget}';
    }
    
    public function description(): string
    {
        return 'Delete a failed queue job from the log.';
    }

    public function handle(): int
    {
        $id = (int) $this->argument('id');

        $logPath = storage_path('logs/failed_jobs.log');

        if (!file_exists($logPath) || filesize($logPath) === 0) {
            $this->info('No failed jobs found.');
            return 0;
        }

        $content = file_get_contents($logPath);
        $entries = explode("\n\n---\n\n", trim($content));

        $index = $id - 1;

        // Remove the specified entry
        unset($entries[$index]);

        // Rebuild the log content
        $newContent = implode("\n\n---\n\n", $entries);

        // Add the trailing separator back if there's content left, to maintain format
        if (!empty(trim($newContent))) {
            $newContent .= "\n\n---\n";
        }

        file_put_contents($logPath, $newContent);

        $this->info("✔ Failed job with ID [{$id}] has been forgotten.");
        return 0;
    }
}