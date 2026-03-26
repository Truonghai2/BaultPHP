<?php

namespace Core\Queue;

use Core\Application;
use Core\Contracts\Queue\Job;

/**
 * Pending chain of jobs to be executed sequentially.
 * 
 * Each job in the chain only executes if the previous job succeeds.
 * If any job fails, the chain stops.
 * 
 * Usage:
 * Bus::chain([
 *     new DownloadFile($url),
 *     new ProcessFile($file),
 *     new UploadToS3($file),
 *     new NotifyUser($user),
 * ])->catch(function ($job, $exception) {
 *     Log::error('Chain failed: ' . $exception->getMessage());
 * })->dispatch();
 */
class PendingChain
{
    protected array $jobs = [];
    protected ?\Closure $catchCallback = null;
    protected ?string $onQueue = null;
    protected ?string $onConnection = null;

    public function __construct(
        protected Application $app,
        array $jobs
    ) {
        $this->jobs = $jobs;
    }

    /**
     * Set the callback for when the chain fails.
     */
    public function catch(\Closure $callback): self
    {
        $this->catchCallback = $callback;
        return $this;
    }

    /**
     * Set the queue for the chain jobs.
     */
    public function onQueue(string $queue): self
    {
        $this->onQueue = $queue;
        return $this;
    }

    /**
     * Set the connection for the chain jobs.
     */
    public function onConnection(string $connection): self
    {
        $this->onConnection = $connection;
        return $this;
    }

    /**
     * Dispatch the job chain.
     * 
     * The first job will be dispatched immediately.
     * Subsequent jobs are attached to it as a chain.
     */
    public function dispatch(): mixed
    {
        if (empty($this->jobs)) {
            return null;
        }

        // Get the first job
        $firstJob = array_shift($this->jobs);

        // Attach remaining jobs as chain
        if ($firstJob instanceof Job) {
            $firstJob->chainedJobs = $this->jobs;
            $firstJob->chainCatchCallback = $this->catchCallback;
        }

        // Dispatch the first job
        $queue = $this->app->make('queue');
        
        if ($this->onConnection) {
            $connection = $queue->connection($this->onConnection);
        } else {
            $connection = $queue->connection();
        }

        $connection->push($firstJob, $this->onQueue);

        return $firstJob->getJobId();
    }

    /**
     * Dispatch the chain if a condition is true.
     */
    public function dispatchIf(bool $condition): mixed
    {
        return $condition ? $this->dispatch() : null;
    }

    /**
     * Dispatch the chain unless a condition is true.
     */
    public function dispatchUnless(bool $condition): mixed
    {
        return !$condition ? $this->dispatch() : null;
    }
}
