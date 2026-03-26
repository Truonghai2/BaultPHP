<?php

namespace Core\Queue\Middleware;

use Core\Contracts\Queue\Job;
use Closure;
use Throwable;

/**
 * Throttle jobs based on exception rate.
 * 
 * If a job throws too many exceptions within a time window,
 * subsequent jobs will be delayed using exponential backoff.
 * 
 * Useful for handling flaky external services.
 * 
 * Usage:
 * public function middleware() {
 *     return [
 *         new ThrottlesExceptions(10, 5)  // 10 exceptions per 5 minutes
 *     ];
 * }
 */
class ThrottlesExceptions implements Middleware
{
    protected int $maxAttempts;
    protected int $decayMinutes;
    protected string $prefix = 'job:throttle:';

    /**
     * @param int $maxAttempts Maximum exceptions allowed
     * @param int $decayMinutes Time window in minutes
     */
    public function __construct(
        int $maxAttempts = 10,
        int $decayMinutes = 5
    ) {
        $this->maxAttempts = $maxAttempts;
        $this->decayMinutes = $decayMinutes;
    }

    public function handle(Job $job, Closure $next): mixed
    {
        $key = $this->getKey($job);
        $redis = app('redis')->connection();

        // Check if throttled
        $attempts = (int) $redis->get($key) ?: 0;

        if ($attempts >= $this->maxAttempts) {
            // Throttled: Calculate exponential backoff delay
            $delay = min(($attempts - $this->maxAttempts + 1) ** 2 * 60, 3600);
            $job->release($delay);
            return null;
        }

        try {
            // Execute job
            $result = $next($job);

            // Success: Reset counter
            $redis->del($key);

            return $result;
        } catch (Throwable $e) {
            // Increment exception counter
            $redis->incr($key);
            $redis->expire($key, $this->decayMinutes * 60);

            // Re-throw exception
            throw $e;
        }
    }

    /**
     * Get the throttle key for the job.
     */
    protected function getKey(Job $job): string
    {
        return $this->prefix . get_class($job);
    }
}
