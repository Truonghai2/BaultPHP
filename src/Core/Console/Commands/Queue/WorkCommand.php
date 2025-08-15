<?php

namespace Core\Console\Commands\Queue;

use Core\Console\Contracts\BaseCommand;
use Core\Queue\QueueManager;
use Core\Queue\QueueWorker;
use Psr\Log\LoggerInterface;

class WorkCommand extends BaseCommand
{
    protected bool $shouldQuit = false;

    public function __construct(
        protected QueueManager $queueManager,
        protected LoggerInterface $logger,
        protected QueueWorker $worker,
    ) {
        parent::__construct();
    }

    public function signature(): string
    {
        return 'queue:work {--connection= : The name of the queue connection to work} {--queue= : The name of the specific queue to listen on} {--sleep=3 : Seconds to sleep when no job is available} {--once : Only process the next job on the queue}';
    }

    public function description(): string
    {
        return 'Start processing jobs on the queue';
    }

    public function handle(): int
    {
        $this->listenForSignals();

        $connectionName = $this->option('connection') ?: $this->queueManager->getDefaultDriver();
        $queueName = $this->option('queue');
        $sleep = (int) $this->option('sleep');

        $queueConnection = $this->queueManager->connection($connectionName);

        $displayQueue = $queueName ?: 'default';
        $this->io->writeln("<info>Worker started for '{$connectionName}' connection on '{$displayQueue}' queue.</info>");

        while (!$this->shouldQuit) {
            $job = $queueConnection->pop($queueName);

            if ($job) {
                $jobName = get_class($job);
                $this->io->writeln("<comment>Processing:</comment> {$jobName}");

                // Delegate the entire processing logic to the worker.
                $this->worker->process($connectionName, $job);

                $this->io->writeln("<info>Processed:</info>  {$jobName}");
            } else {
                sleep($sleep);
            }

            if ($this->option('once')) {
                break;
            }
        }

        return 0;
    }

    /**
     * Lắng nghe các tín hiệu hệ thống để dừng worker một cách an toàn.
     */
    protected function listenForSignals(): void
    {
        if (!extension_loaded('pcntl')) {
            return;
        }

        pcntl_async_signals(true);

        pcntl_signal(SIGTERM, function () {
            $this->logger->info('SIGTERM received, worker shutting down.');
            $this->shouldQuit = true;
        });

        pcntl_signal(SIGINT, function () {
            $this->logger->info('SIGINT received (Ctrl+C), worker shutting down.');
            $this->shouldQuit = true;
        });
    }
}
