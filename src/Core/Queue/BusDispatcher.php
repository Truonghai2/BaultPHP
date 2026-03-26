<?php

namespace Core\Queue;

use Core\Application;
use Core\Contracts\Queue\Job;
use Core\Contracts\Queue\Queue;
use Closure;

/**
 * Bus Dispatcher - Central dispatcher for commands, queries, and batches.
 * 
 * Inspired by CQRS Command/Query Bus pattern from DDD.
 * 
 * Features:
 * - Dispatch jobs to queue
 * - Batch multiple jobs
 * - Chain jobs sequentially
 * - Apply middleware pipeline
 * 
 * @see docs/DDD_HEXAGONAL_CQRS_ES_EDA_ANALYSIS.md
 */
class BusDispatcher
{
    protected Queue $queue;

    public function __construct(protected Application $app)
    {
        $this->queue = $this->app->make('queue')->connection();
    }

    /**
     * Dispatch a job to the queue.
     * 
     * @param Job $job The job to dispatch
     * @param string|null $queue The queue name
     * @return mixed Job ID or result
     */
    public function dispatch(Job $job, ?string $queue = null): mixed
    {
        $this->queue->push($job, $queue);
        
        return $job->getJobId();
    }

    /**
     * Dispatch a job synchronously (execute immediately).
     * 
     * @param Job $job The job to execute
     * @return mixed Result of job execution
     */
    public function dispatchNow(Job $job): mixed
    {
        return $this->app->call([$job, 'handle']);
    }

    /**
     * Dispatch a job if a condition is true.
     */
    public function dispatchIf(bool $condition, Job $job, ?string $queue = null): mixed
    {
        if ($condition) {
            return $this->dispatch($job, $queue);
        }

        return null;
    }

    /**
     * Dispatch a job unless a condition is true.
     */
    public function dispatchUnless(bool $condition, Job $job, ?string $queue = null): mixed
    {
        return $this->dispatchIf(!$condition, $job, $queue);
    }

    /**
     * Create a batch of jobs.
     * 
     * Usage:
     * Bus::batch([
     *     new ProcessOrder($order1),
     *     new ProcessOrder($order2),
     * ])->then(function (Batch $batch) {
     *     Log::info('All done!');
     * })->dispatch();
     * 
     * @param array $jobs Array of jobs
     * @return PendingBatch
     */
    public function batch(array $jobs): PendingBatch
    {
        return new PendingBatch($this->app, $jobs);
    }

    /**
     * Find a batch by ID.
     */
    public function findBatch(string $batchId): ?Batch
    {
        $repository = $this->app->make(BatchRepository::class);
        return $repository->find($batchId);
    }

    /**
     * Create a job chain.
     * 
     * Usage:
     * Bus::chain([
     *     new DownloadFile($url),
     *     new ProcessFile($file),
     *     new UploadFile($file),
     * ])->dispatch();
     * 
     * @param array $jobs Array of jobs to chain
     * @return PendingChain
     */
    public function chain(array $jobs): PendingChain
    {
        return new PendingChain($this->app, $jobs);
    }

    /**
     * Dispatch jobs after a delay.
     */
    public function later(int $delay, Job $job, ?string $queue = null): mixed
    {
        $this->queue->later($delay, $job, $queue);
        
        return $job->getJobId();
    }

    /**
     * Get all pending jobs count.
     */
    public function size(?string $queue = null): int
    {
        return $this->queue->size($queue);
    }

    /**
     * Bulk dispatch multiple jobs.
     */
    public function bulk(array $jobs, ?string $queue = null): array
    {
        $jobIds = [];

        foreach ($jobs as $job) {
            $jobIds[] = $this->dispatch($job, $queue);
        }

        return $jobIds;
    }
}
