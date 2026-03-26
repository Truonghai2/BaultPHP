<?php

namespace Core\Queue;

use Core\Application;
use Core\Contracts\Exceptions\Handler as ExceptionHandler;
use Core\Contracts\Queue\FailedJobProviderInterface;
use Core\Contracts\Queue\Job;
use Core\Contracts\Queue\Queue;
use Core\Events\Dispatcher;
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
     * @param \Core\Contracts\Exceptions\Handler $exceptionHandler The application's exception handler.
     * @param \Core\Events\Dispatcher $events The event dispatcher.
     * @param \Core\Application $app The application instance.
     */
    public function __construct(
        protected QueueManager $manager,
        protected FailedJobProviderInterface $failer,
        protected ExceptionHandler $exceptionHandler,
        protected Dispatcher $events,
        protected Application $app,
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
        $this->raiseBeforeJobEvent($connectionName, $job);

        try {
            // Execute job through middleware pipeline
            $this->executeJobWithMiddleware($job);

            $job->delete();

            // Handle batch completion
            $this->handleBatchCompletion($job);

            // Dispatch next job in chain
            $this->dispatchNextChainedJob($job);

            $this->raiseAfterJobEvent($connectionName, $job);

        } catch (Throwable $e) {
            $this->raiseExceptionOccurredEvent($connectionName, $job, $e);

            // Handle batch failure
            $this->handleBatchFailure($job, $e);

            // Handle chain failure
            $this->handleChainFailure($job, $e);

            if (!$job->isDeleted() && !$job->isReleased()) {
                $this->handleJobFailure($connectionName, $job, $e);
            }
        }
    }

    /**
     * Execute the job through middleware pipeline.
     */
    protected function executeJobWithMiddleware(Job $job): void
    {
        $middleware = method_exists($job, 'middleware') ? $job->middleware() : [];

        if (empty($middleware)) {
            $this->app->call([$job, 'handle']);
            return;
        }

        // Build middleware pipeline
        $pipeline = array_reduce(
            array_reverse($middleware),
            fn($next, $mw) => fn() => $mw->handle($job, $next),
            fn() => $this->app->call([$job, 'handle'])
        );

        $pipeline();
    }

    /**
     * Handle batch completion after successful job execution.
     */
    protected function handleBatchCompletion(Job $job): void
    {
        if (!isset($job->batchId)) {
            return;
        }

        $repository = $this->app->make(\Core\Queue\BatchRepository::class);
        $batch = $repository->find($job->batchId);

        if ($batch) {
            $batch->recordSuccessfulJob($job->getJobId());
        }
    }

    /**
     * Handle batch failure.
     */
    protected function handleBatchFailure(Job $job, Throwable $e): void
    {
        if (!isset($job->batchId)) {
            return;
        }

        $repository = $this->app->make(\Core\Queue\BatchRepository::class);
        $batch = $repository->find($job->batchId);

        if ($batch) {
            $batch->recordFailedJob($job->getJobId(), $e);
        }
    }

    /**
     * Dispatch the next job in the chain.
     */
    protected function dispatchNextChainedJob(Job $job): void
    {
        if (!isset($job->chainedJobs) || empty($job->chainedJobs)) {
            return;
        }

        // Get next job in chain
        $nextJob = array_shift($job->chainedJobs);

        // Pass remaining chain to next job
        if ($nextJob instanceof Job) {
            $nextJob->chainedJobs = $job->chainedJobs;
            $nextJob->chainCatchCallback = $job->chainCatchCallback ?? null;
        }

        // Dispatch next job
        $queue = $this->manager->connection();
        $queue->push($nextJob);
    }

    /**
     * Handle chain failure.
     */
    protected function handleChainFailure(Job $job, Throwable $e): void
    {
        if (!isset($job->chainCatchCallback)) {
            return;
        }

        try {
            $callback = $job->chainCatchCallback;
            $callback($job, $e);
        } catch (Throwable $callbackException) {
            $this->exceptionHandler->report($callbackException);
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
            if ($connectionName === 'swoole') {
                $this->releaseSwooleJob($job);
            } else {
                $job->release();
            }
        }
    }

    /**
     * Release a job back to the Swoole delayed queue.
     */
    protected function releaseSwooleJob(Job $job): void
    {
        /** @var Queue $queue */
        $queue = $this->app->make(Queue::class);
        $delay = ($job->attempts() ** 2) * 5;
        $queue->later($delay, $job);
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
        $this->logFailedJob($connectionName, $job, $e);

        $this->app->call([$job, 'fail'], ['e' => $e]);

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
        $this->exceptionHandler->report($e);

        $this->failer->log(
            $connectionName,
            method_exists($job, 'getQueue') ? $job->getQueue() : 'default',
            $job->getRawBody(),
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
