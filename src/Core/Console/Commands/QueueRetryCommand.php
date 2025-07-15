<?php

namespace Core\Console\Commands;

use Core\Console\Contracts\BaseCommand;
use Core\Queue\QueueManager;
use Throwable;

class QueueRetryCommand extends BaseCommand
{
    public function __construct(private QueueManager $queue)
    {
        parent::__construct();
    }

    public function signature(): string
    {
        return 'queue:retry {id : The ID of the failed job to retry}';
    }
    
    public function description(): string
    {
        return 'Retry a failed queue job.';
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

        if (!isset($entries[$index])) {
            $this->error("Failed job with ID [{$id}] not found.");
            return 1;
        }

        preg_match('/Payload: (.*)/s', $entries[$index], $matches);

        if (!isset($matches[1])) {
            $this->error("Could not extract payload for job ID [{$id}]. Log format might be incorrect.");
            return 1;
        }

        try {
            $job = @unserialize($matches[1]);

            if ($job === false || !$job instanceof \Core\Contracts\Queue\Job) {
                throw new \Exception("Failed to unserialize job payload. The class might not exist or is invalid.");
            }

            $job->tries = 0;
            $this->queue->push($job);
            $this->info("✔ The failed job [" . get_class($job) . "] has been pushed back onto the queue.");

            unset($entries[$index]);
            $newContent = implode("\n\n---\n\n", $entries);
            if (!empty(trim($newContent))) {
                $newContent .= "\n\n---\n\n";
            }
            file_put_contents($logPath, $newContent);
            return 0;
        } catch (Throwable $e) {
            $this->error("Could not retry job: " . $e->getMessage());
            return 1;
        }
    }
}