<?php

namespace Core\Queue\Middleware;

use Core\Contracts\Queue\Job;
use Core\Queue\RateLimiter;
use Closure;

/**
 * Rate limit job execution.
 * 
 * Prevents overwhelming external services or resources by limiting
 * the number of jobs that can execute within a time window.
 * 
 * Uses sliding window rate limiting with Redis.
 * 
 * Usage:
 * public function middleware() {
 *     return [
 *         new RateLimited('api-calls', 10, 60)  // 10 calls per 60 seconds
 *     ];
 * }
 */
class RateLimited implements Middleware
{
    protected string $key;
    protected int $maxAttempts;
    protected int $decaySeconds;

    /**
     * @param string $key Rate limiter key
     * @param int $maxAttempts Maximum attempts allowed
     * @param int $decaySeconds Time window in seconds
     */
    public function __construct(
        string $key,
        int $maxAttempts = 60,
        int $decaySeconds = 60
    ) {
        $this->key = $key;
        $this->maxAttempts = $maxAttempts;
        $this->decaySeconds = $decaySeconds;
    }

    public function handle(Job $job, Closure $next): mixed
    {
        $rateLimiter = app(RateLimiter::class);

        if ($rateLimiter->tooManyAttempts($this->key, $this->maxAttempts)) {
            // Rate limit exceeded, release job with delay
            $retryAfter = $rateLimiter->availableIn($this->key);
            $job->release($retryAfter);
            return null;
        }

        // Record attempt
        $rateLimiter->hit($this->key, $this->decaySeconds);

        // Execute job
        return $next($job);
    }
}
