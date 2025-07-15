<?php

namespace Core\Console\Commands;

use Core\Console\Contracts\BaseCommand;

class QueueFailedCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'queue:failed';
    }
    
    public function description(): string
    {
        return 'List all of the failed queue jobs.';
    }
    
    public function handle(): int
    {
        $logPath = storage_path('logs/failed_jobs.log');

        if (!file_exists($logPath) || filesize($logPath) === 0) {
            $this->info('No failed jobs found.');
            return 0;
        }

        $content = file_get_contents($logPath);

        // Split the log file into individual job entries
        $entries = explode("\n\n---\n\n", trim($content));
        $jobs = [];
        $id = 1;

        foreach ($entries as $entry) {
            if (empty(trim($entry))) {
                continue;
            }

            // Use regex to parse the structured log entry
            preg_match(
                '/\[(?<timestamp>.*?)\] Failed Job: (?<job>.*?)\nException: (?<exception>.*?)\nMessage: (?<message>.*?)\nTrace:/s',
                $entry,
                $matches
            );

            if (!empty($matches)) {
                $jobs[] = [
                    'id' => $id++,
                    'timestamp' => $matches['timestamp'] ?? 'N/A',
                    'job' => $matches['job'] ?? 'N/A',
                    'message' => trim($matches['message'] ?? 'N/A'),
                ];
            }
        }

        if (empty($jobs)) {
            $this->comment('Could not parse any failed jobs from the log file.');
            return 0;
        }

        $tableData = array_map(function ($job) {
            $message = preg_replace('/\s+/', ' ', $job['message']); // Collapse whitespace
            if (mb_strlen($message) > 70) {
                $message = mb_substr($message, 0, 67) . '...';
            }
            return [$job['id'], $job['timestamp'], $job['job'], $message];
        }, $jobs);

        $this->io->table(['ID', 'Failed At', 'Job Class', 'Error'], $tableData);
        $this->comment("Use `queue:retry <id>` or `queue:forget <id>` to manage these jobs.");
        return 0;
    }
}