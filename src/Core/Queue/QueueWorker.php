<?php

namespace Core\Queue;

use App\Exceptions\Handler;
use Core\Contracts\Queue\FailedJobProviderInterface;
use Core\Contracts\Queue\Job;
use Throwable;

/**
 * Class QueueWorker
 * Responsible for processing jobs from the queue.
 */
class QueueWorker
{
    /**
     * Create a new queue worker.
     * The worker is decoupled from the concrete failed job provider by depending on the interface.
     * This allows for easier testing and swapping of implementations.
     *
     * @param \Core\Queue\QueueManager $manager The queue manager to resolve connections.
     * @param \Core\Contracts\Queue\FailedJobProviderInterface $failer The provider for logging failed jobs.
     * @param \App\Exceptions\Handler $exceptionHandler The application's exception handler.
     */
    public function __construct(
        protected QueueManager $manager,
        protected FailedJobProviderInterface $failer,
        protected Handler $exceptionHandler
    ) {
    }

    /**
     * Process the next job on the given queue connection.
     *
     * @param string $connectionName The name of the queue connection to use.
     * @param string|null $queue The specific queue to pop from.
     * @return void
     */
    public function runNextJob(string $connectionName, ?string $queue = null): void
    {
        $connection = $this->manager->connection($connectionName);

        $job = $connection->pop($queue);

        if ($job) {
            $this->process($connectionName, $job);
        }
    }

    /**
     * Process the given job.
     *
     * @param string $connectionName The name of the connection the job came from.
     * @param \Core\Contracts\Queue\Job $job The job instance to process.
     * @return void
     */
    public function process(string $connectionName, Job $job): void
    {
        try {
            // Execute the job's main logic.
            $job->handle();
        } catch (Throwable $e) {
            // Report the exception to the application's central handler.
            $this->exceptionHandler->report($e);

            // If the job throws an exception, we will log it as a failed job.
            $this->logFailedJob($connectionName, $job, $e);
        }
    }

    /**
     * Log a failed job using the failed job provider.
     *
     * @param string $connectionName
     * @param \Core\Contracts\Queue\Job $job
     * @param \Throwable $e
     * @return void
     */
    protected function logFailedJob(string $connectionName, Job $job, Throwable $e): void
    {
        // Đây là điểm mấu chốt: chúng ta gọi phương thức `log` trên interface.
        // DI container sẽ cung cấp implementation cụ thể (FailedJobProvider) lúc runtime.
        $this->failer->log(
            $connectionName,
            property_exists($job, 'queue') && $job->queue ? $job->queue : 'default',
            serialize($job),
            $e
        );
    }
}
