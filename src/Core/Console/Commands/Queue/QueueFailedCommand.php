<?php

namespace Core\Console\Commands\Queue;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Core\Contracts\Queue\FailedJobProviderInterface;
use Core\Queue\FailedJob; // The model is still needed for mapping

class QueueFailedCommand extends BaseCommand
{
    public function __construct(Application $app, protected FailedJobProviderInterface $failedJobProvider)
    {
        parent::__construct($app);
    }

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
        $failedJobs = $this->failedJobProvider->all();

        if ($failedJobs->isEmpty()) {
            $this->info('No failed jobs found in the database.');
            return 0;
        }

        $headers = ['ID', 'UUID', 'Connection', 'Queue', 'Failed At', 'Exception'];
        $rows = $failedJobs->map(function (FailedJob $job) {
            return [
                $job->id,
                $job->uuid,
                $job->connection,
                $job->queue,
                $job->created_at->format('Y-m-d H:i:s'),
                $this->formatException($job->exception),
            ];
        })->toArray();
        $this->io->table($headers, $rows);
        $this->comment('Use `queue:retry <uuid>` or `queue:forget <uuid>` to manage these jobs.');
        return 0;
    }

    private function formatException(string $exception): string
    {
        $exception = preg_replace('/\s+/', ' ', $exception);
        return mb_strlen($exception) > 100 ? mb_substr($exception, 0, 97) . '...' : $exception;
    }
}
