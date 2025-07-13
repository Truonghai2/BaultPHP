<?php

namespace Core\Console;

use Core\Console\Contracts\BaseCommand;
use Core\Queue\FailedJobProvider;
use Core\Queue\QueueManager;
use Throwable;

class QueueWorkCommand extends BaseCommand
{
    protected string $signature = 'queue:work {connection? : The name of the queue connection to work} {--queue= : The name of the queue to work} {--tries=1 : The number of times to attempt a job before logging it failed} {--sleep=3 : Number of seconds to sleep when no job is available}';
    protected string $description = 'Start processing jobs on the queue as a daemon.';

    public function __construct(
        private QueueManager $queueManager,
        private FailedJobProvider $failedJobProvider
    ) {
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
        $connectionName = $this->argument('connection') ?? $this->queueManager->getDefaultDriver();
        $queueName = $this->option('queue') ?? config("queue.connections.{$connectionName}.queue", 'default');
        $tries = (int) $this->option('tries', 1);
        $sleep = (int) $this->option('sleep', 3);

        $this->io->title("Queue Worker Started");
        $this->io->writeln("Connection: <info>{$connectionName}</info>");
        $this->io->writeln("Queue:      <info>{$queueName}</info>");
        $this->io->writeln("Max Tries:  <info>{$tries}</info>");
        $this->io->writeln('Press Ctrl+C to stop the worker.');
        $this->io->newLine();

        while (true) {
            $job = $this->queueManager->connection($connectionName)->pop($queueName);
            
            try {
                if ($job) {
                    if ($job->attempts() >= $tries) {
                        $this->failJob($job, new \RuntimeException("Job has been attempted too many times."));
                        continue;
                    }
                    
                    $this->io->writeln("<fg=yellow>Processing:</> " . $job->getName() . " (Attempt " . ($job->attempts() + 1) . "/{$tries})");
                    $job->run();
                    $this->io->writeln("<fg=green>Processed:</>  " . $job->getName());
                } else {
                    sleep($sleep);
                }
            } catch (Throwable $e) {
                if ($job) {
                    $job->release();
                }
                $this->io->error("Job failed and released: " . ($job ? $job->getName() : 'Unknown Job') . " - " . $e->getMessage());
            }
        }
    }

    /**
     * Handle a job that has failed permanently.
     */
    protected function failJob(\Core\Contracts\Queue\Job $job, Throwable $exception): void
    {
        $this->io->error("<fg=red;options=bold>FAILED PERMANENTLY:</> " . $job->getName());
        $this->failedJobProvider->log(
            $job->getConnectionName(),
            $job->getQueue(),
            $job->getRawBody(),
            $exception
        );
        $job->delete();
    }
}