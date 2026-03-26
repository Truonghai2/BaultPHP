<?php

namespace Core\Queue\Middleware;

use Core\Contracts\Queue\Job;
use Closure;

/**
 * Prevent overlapping execution of jobs with the same key.
 * 
 * Useful for jobs that should not run concurrently, such as:
 * - Processing the same resource
 * - API calls to the same endpoint
 * - Critical sections
 * 
 * Uses Redis for distributed locking.
 * 
 * Usage:
 * public function middleware() {
 *     return [
 *         new WithoutOverlapping('order-' . $this->orderId, 60)
 *     ];
 * }
 */
class WithoutOverlapping implements Middleware
{
    protected string $key;
    protected int $expiresAfter;
    protected int $releaseAfter;

    /**
     * @param string $key Unique key for the lock
     * @param int $expiresAfter Time in seconds before lock expires (default: 0 = no expiry)
     * @param int $releaseAfter Time in seconds to wait before retrying (default: 0)
     */
    public function __construct(
        string $key,
        int $expiresAfter = 0,
        int $releaseAfter = 0
    ) {
        $this->key = $key;
        $this->expiresAfter = $expiresAfter;
        $this->releaseAfter = $releaseAfter;
    }

    public function handle(Job $job, Closure $next): mixed
    {
        $redis = app('redis')->connection();
        $lockKey = 'job:lock:' . $this->key;

        // Try to acquire lock
        $acquired = $this->expiresAfter > 0
            ? $redis->set($lockKey, '1', ['NX', 'EX' => $this->expiresAfter])
            : $redis->set($lockKey, '1', ['NX']);

        if (!$acquired) {
            // Lock is held by another job
            if ($this->releaseAfter > 0) {
                // Release job back to queue
                $job->release($this->releaseAfter);
            } else {
                // Delete job (skip it)
                $job->delete();
            }
            return null;
        }

        try {
            // Execute job
            return $next($job);
        } finally {
            // Release lock
            $redis->del($lockKey);
        }
    }
}
