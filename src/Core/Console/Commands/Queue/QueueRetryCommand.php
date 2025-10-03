<?php

namespace Core\Console\Commands\Queue;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Core\Contracts\Queue\FailedJobProviderInterface;
use Core\Queue\QueueManager;
use Throwable;

class QueueRetryCommand extends BaseCommand
{
    public function __construct(
        Application $app,
        private QueueManager $queue,
        private FailedJobProviderInterface $failedJobProvider,
    ) {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'queue:retry {uuid : The UUID of the failed job to retry}';
    }
    public function description(): string
    {
        return 'Retry a failed queue job.';
    }
    public function handle(): int
    {
        $uuid = $this->argument('uuid');

        $failedJob = $this->failedJobProvider->find($uuid);
        if (!$failedJob) {
            $this->error("Failed job with UUID [{$uuid}] not found in the database.");
            return 1;
        }

        // Assuming your payload is serialized
        $job = @unserialize($failedJob->payload);

        try {
            if ($job === false || !$job instanceof \Core\Contracts\Queue\Job) {
                throw new \Exception("Failed to unserialize job payload for UUID [{$uuid}]. The job class might not exist or be invalid.");
            }

            // Push the job back onto the queue
            $this->queue->push($job);
            $this->info('✔ The failed job [' . get_class($job) . '] has been pushed back onto the queue.');

            // Delete the failed job record after successful retry
            $this->failedJobProvider->forget($uuid);
            $this->info("✔ Failed job record with UUID [{$uuid}] has been removed from the database.");

            return 0;
        } catch (Throwable $e) {
            $this->error("Could not retry job with UUID [{$uuid}]: " . $e->getMessage());
            return 1;
        }
    }
}
