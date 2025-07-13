<?php

namespace Core\Console;

use Core\Console\Contracts\BaseCommand;

class QueueForgetCommand extends BaseCommand
{
    protected string $signature = 'queue:forget {id}';
    protected string $description = 'Delete a failed queue job from the log.';

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
        $id = (int)($args[0] ?? 0);

        if ($id <= 0) {
            $this->io->error('Please provide a valid numeric ID for the job to forget.');
            return;
        }

        $logPath = storage_path('logs/failed_jobs.log');

        if (!file_exists($logPath) || filesize($logPath) === 0) {
            $this->io->info('No failed jobs found.');
            return;
        }

        $content = file_get_contents($logPath);
        $entries = explode("\n\n---\n\n", trim($content));

        // Adjust ID to be 0-indexed for the array
        $index = $id - 1;

        if (!isset($entries[$index])) {
            $this->io->error("Failed job with ID [{$id}] not found.");
            return;
        }

        // Remove the specified entry
        unset($entries[$index]);

        // Rebuild the log content
        $newContent = implode("\n\n---\n\n", $entries);

        // Add the trailing separator back if there's content left, to maintain format
        if (!empty(trim($newContent))) {
            $newContent .= "\n\n---\n\n";
        }

        file_put_contents($logPath, $newContent);

        $this->io->success("Failed job with ID [{$id}] has been forgotten.");
    }
}