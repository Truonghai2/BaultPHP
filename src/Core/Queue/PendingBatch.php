<?php

namespace Core\Queue;

use Closure;
use Core\Application;
use Core\Contracts\Queue\Job;

/**
 * Pending batch that will be dispatched to the queue.
 * 
 * Usage:
 * Bus::batch([
 *     new ProcessPayment($order1),
 *     new ProcessPayment($order2),
 * ])->then(function (Batch $batch) {
 *     // All jobs completed
 * })->catch(function (Batch $batch, $e) {
 *     // First failure
 * })->name('Process Orders')->dispatch();
 */
class PendingBatch
{
    protected array $jobs = [];
    protected string $name = '';
    protected ?Closure $then = null;
    protected ?Closure $catch = null;
    protected ?Closure $finally = null;
    protected bool $allowFailures = false;
    protected ?string $onQueue = null;
    protected ?string $onConnection = null;

    public function __construct(
        protected Application $app,
        array $jobs
    ) {
        $this->jobs = $jobs;
    }

    /**
     * Set the batch name.
     */
    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Add more jobs to the batch.
     */
    public function add(array $jobs): self
    {
        $this->jobs = array_merge($this->jobs, $jobs);
        return $this;
    }

    /**
     * Set callback for when all jobs complete successfully.
     */
    public function then(Closure $callback): self
    {
        $this->then = $callback;
        return $this;
    }

    /**
     * Set callback for first job failure.
     */
    public function catch(Closure $callback): self
    {
        $this->catch = $callback;
        return $this;
    }

    /**
     * Set callback for when batch finishes.
     */
    public function finally(Closure $callback): self
    {
        $this->finally = $callback;
        return $this;
    }

    /**
     * Allow the batch to continue even if jobs fail.
     */
    public function allowFailures(bool $allow = true): self
    {
        $this->allowFailures = $allow;
        return $this;
    }

    /**
     * Set the queue for the batch jobs.
     */
    public function onQueue(string $queue): self
    {
        $this->onQueue = $queue;
        return $this;
    }

    /**
     * Set the connection for the batch jobs.
     */
    public function onConnection(string $connection): self
    {
        $this->onConnection = $connection;
        return $this;
    }

    /**
     * Dispatch the batch to the queue.
     */
    public function dispatch(): Batch
    {
        $batch = new Batch(
            $this->app,
            name: $this->name,
            totalJobs: count($this->jobs)
        );

        if ($this->then) {
            $batch->then($this->then);
        }

        if ($this->catch) {
            $batch->catch($this->catch);
        }

        if ($this->finally) {
            $batch->finally($this->finally);
        }

        $batch->allowFailures($this->allowFailures);

        // Store the batch
        $repository = $this->app->make(BatchRepository::class);
        $repository->store($batch);

        // Dispatch all jobs
        $queue = $this->app->make('queue');
        
        foreach ($this->jobs as $job) {
            // Add batch information to job
            if ($job instanceof Job) {
                $job->batchId = $batch->id();
            }

            // Dispatch job
            if ($this->onConnection) {
                $connection = $queue->connection($this->onConnection);
            } else {
                $connection = $queue->connection();
            }

            $connection->push($job, $this->onQueue);
        }

        return $batch;
    }

    /**
     * Dispatch the batch if a boolean condition is true.
     */
    public function dispatchIf(bool $condition): ?Batch
    {
        return $condition ? $this->dispatch() : null;
    }

    /**
     * Dispatch the batch unless a boolean condition is true.
     */
    public function dispatchUnless(bool $condition): ?Batch
    {
        return !$condition ? $this->dispatch() : null;
    }
}
