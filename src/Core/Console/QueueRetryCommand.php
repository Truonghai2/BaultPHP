<?php

namespace Core\Console;

use Core\Console\Contracts\BaseCommand;
use Core\Queue\QueueManager;
use Throwable;

class QueueRetryCommand extends BaseCommand
{
    protected string $signature = 'queue:retry {id}';
    protected string $description = 'Retry a failed queue job.';

    public function __construct(private QueueManager $queue)
    {
        parent::__construct();
    }

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
            $this->io->error('Please provide a valid numeric ID for the job to retry.');
            return;
        }

        $logPath = storage_path('logs/failed_jobs.log');

        if (!file_exists($logPath) || filesize($logPath) === 0) {
            $this->io->info('No failed jobs found.');
            return;
        }

        $content = file_get_contents($logPath);
        $entries = explode("\n\n---\n\n", trim($content));

        $index = $id - 1;

        if (!isset($entries[$index])) {
            $this->io->error("Failed job with ID [{$id}] not found.");
            return;
        }

        preg_match('/Payload: (.*)/s', $entries[$index], $matches);

        if (!isset($matches[1])) {
            $this->io->error("Could not extract payload for job ID [{$id}]. Log format might be incorrect.");
            return;
        }

        try {
            $job = @unserialize($matches[1]);

            if ($job === false || !$job instanceof \Core\Contracts\Queue\Job) {
                throw new \Exception("Failed to unserialize job payload. The class might not exist or is invalid.");
            }

            $job->tries = 0;
            $this->queue->push($job);
            $this->io->success("The failed job [" . get_class($job) . "] has been pushed back onto the queue.");

            unset($entries[$index]);
            $newContent = implode("\n\n---\n\n", $entries);
            if (!empty(trim($newContent))) {
                $newContent .= "\n\n---\n\n";
            }
            file_put_contents($logPath, $newContent);
        } catch (Throwable $e) {
            $this->io->error("Could not retry job: " . $e->getMessage());
        }
    }
}