<?php

namespace Core\Queue\Middleware;

use Core\Contracts\Queue\Job;
use Closure;

/**
 * Job Middleware interface.
 * 
 * Middleware can intercept job execution to:
 * - Validate job data
 * - Rate limit execution
 * - Prevent overlapping execution
 * - Handle exceptions
 * - Add logging/metrics
 * 
 * Usage:
 * class ProcessOrder extends BaseJob {
 *     public function middleware() {
 *         return [
 *             new WithoutOverlapping('order-' . $this->orderId),
 *             new RateLimited('api-calls', 10, 60),
 *         ];
 *     }
 * }
 */
interface Middleware
{
    /**
     * Handle the job through the middleware.
     *
     * @param Job $job The job being processed
     * @param Closure $next The next middleware in the pipeline
     * @return mixed
     */
    public function handle(Job $job, Closure $next): mixed;
}
