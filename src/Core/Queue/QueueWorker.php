<?php

namespace Core\Queue;

use Core\Contracts\Exceptions\Handler as ExceptionHandler;
use Core\Contracts\Queue\FailedJobProviderInterface;
use Core\Contracts\Queue\Job;
use Core\Events\Dispatcher;
use Throwable;

/**
 * Class QueueWorker
 * Responsible for processing jobs from the queue.
 */
class QueueWorker
{
    protected Dispatcher $events;

    /**
     * Create a new queue worker.
     * The worker is decoupled from the concrete failed job provider by depending on the interface.
     * This allows for easier testing and swapping of implementations.
     *
     * @param \Core\Queue\QueueManager $manager The queue manager to resolve connections.
     * @param \Core\Contracts\Queue\FailedJobProviderInterface $failer The provider for logging failed jobs.
     * @param \Core\Contracts\Exceptions\Handler $exceptionHandler The application's exception handler.
     * @param \Core\Contracts\Events\Dispatcher $events The event dispatcher.
     */
    public function __construct(
        protected QueueManager $manager,
        protected FailedJobProviderInterface $failer,
        protected ExceptionHandler $exceptionHandler,
        ?Dispatcher $events = null,
    ) {
        $this->events = $events ?? app(Dispatcher::class);
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
        // Fire an event before processing the job.
        $this->raiseBeforeJobEvent($connectionName, $job);

        try {
            if ($this->hasExceededMaxTries($job)) {
                $this->failJob($connectionName, $job, new \RuntimeException('Job has exceeded the maximum number of attempts.'));
                return;
            }

            // Execute the job's main logic.
            $job->handle();

            // If handle() is successful, delete the job from the queue.
            $job->delete();

            // Fire an event after a job has been processed.
            $this->raiseAfterJobEvent($connectionName, $job);

        } catch (Throwable $e) {
            // Fire an event for the exception that occurred.
            $this->raiseExceptionOccurredEvent($connectionName, $job, $e);

            // If an exception occurs, check if the job should be retried.
            if (!$job->isDeleted()) {
                // If the job is not released, handle the failure.
                if (!$job->isReleased()) {
                    $this->handleJobFailure($connectionName, $job, $e);
                }
            }
        }
    }

    /**
     * Handle a job failure.
     *
     * @param  string  $connectionName
     * @param  \Core\Contracts\Queue\Job  $job
     * @param  \Throwable  $e
     * @return void
     */
    protected function handleJobFailure(string $connectionName, Job $job, Throwable $e): void
    {
        if ($this->hasExceededMaxTries($job)) {
            $this->failJob($connectionName, $job, $e);
        } else {
            // Release the job back to the queue for a later attempt.
            $job->release();
        }
    }

    /**
     * Mark a job as failed, log it, and delete it.
     *
     * @param string $connectionName
     * @param Job $job
     * @param Throwable $e
     */
    protected function failJob(string $connectionName, Job $job, Throwable $e): void
    {
        // 1. Log the job to the failed job provider (e.g., database).
        $this->logFailedJob($connectionName, $job, $e);

        // 2. Call the job's own fail method for any custom logic.
        $job->fail($e);

        // 3. Fire the JobFailed event.
        $this->raiseJobFailedEvent($connectionName, $job, $e);
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
        // Report the exception to the application's central handler first.
        $this->exceptionHandler->report($e);

        // Now, log the job to the failed job provider.
        $this->failer->log(
            $connectionName,
            // Use getRawBody() to get the original payload.
            // This avoids issues with a modified job object.
            $job->getRawBody(),
            // The queue name should be a property on the job instance.
            $e,
        );
    }

    /**
     * Determine if the job has exceeded the maximum number of attempts.
     *
     * @param  \Core\Contracts\Queue\Job  $job
     * @return bool
     */
    protected function hasExceededMaxTries(Job $job): bool
    {
        $maxTries = $job->maxTries();
        $attempts = $job->attempts();

        // Nếu maxTries không được set (null), job sẽ được thử lại vô hạn.
        return !is_null($maxTries) && $attempts >= $maxTries;
    }

    /**
     * Raise the before job event.
     */
    protected function raiseBeforeJobEvent(string $connectionName, Job $job): void
    {
        $this->events->dispatch(new \Core\Events\Queue\JobProcessing($connectionName, $job));
    }

    /**
     * Raise the after job event.
     */
    protected function raiseAfterJobEvent(string $connectionName, Job $job): void
    {
        $this->events->dispatch(new \Core\Events\Queue\JobProcessed($connectionName, $job));
    }

    /**
     * Raise the exception occurred event.
     */
    protected function raiseExceptionOccurredEvent(string $connectionName, Job $job, Throwable $e): void
    {
        $this->events->dispatch(new \Core\Events\Queue\JobExceptionOccurred($connectionName, $job, $e));
    }

    /**
     * Raise the job failed event.
     */
    protected function raiseJobFailedEvent(string $connectionName, Job $job, Throwable $e): void
    {
        $this->events->dispatch(new \Core\Events\Queue\JobFailed($connectionName, $job, $e));
    }
}
