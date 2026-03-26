<?php

namespace Core\Queue\Middleware;

use Core\Contracts\Queue\Job;
use Core\Queue\BatchRepository;
use Closure;

/**
 * Skip job execution if its batch has been cancelled.
 * 
 * When a batch is cancelled (e.g., due to a failure), remaining jobs
 * in the queue should be skipped to save resources.
 * 
 * Usage:
 * public function middleware() {
 *     return [
 *         new SkipIfBatchCancelled()
 *     ];
 * }
 */
class SkipIfBatchCancelled implements Middleware
{
    public function handle(Job $job, Closure $next): mixed
    {
        // Check if job belongs to a batch
        if (!isset($job->batchId)) {
            return $next($job);
        }

        // Check if batch is cancelled
        $repository = app(BatchRepository::class);
        $batch = $repository->find($job->batchId);

        if ($batch && $batch->cancelled()) {
            // Delete job without executing
            $job->delete();
            return null;
        }

        // Execute job
        return $next($job);
    }
}
