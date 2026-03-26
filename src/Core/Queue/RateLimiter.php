<?php

namespace Core\Queue;

use Redis;

/**
 * Rate Limiter using Redis.
 * 
 * Implements sliding window rate limiting for job execution.
 * 
 * Usage:
 * $limiter = app(RateLimiter::class);
 * 
 * if ($limiter->tooManyAttempts('api-calls', 10)) {
 *     // Rate limit exceeded
 *     $seconds = $limiter->availableIn('api-calls');
 *     echo "Retry in $seconds seconds";
 * } else {
 *     $limiter->hit('api-calls', 60);  // Record attempt, expires in 60s
 *     // Execute action
 * }
 */
class RateLimiter
{
    protected Redis $redis;
    protected string $prefix = 'rate_limiter:';

    public function __construct()
    {
        $this->redis = app('redis')->connection();
    }

    /**
     * Determine if the given key has been "accessed" too many times.
     *
     * @param string $key
     * @param int $maxAttempts
     * @return bool
     */
    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        $key = $this->prefix . $key;

        if ($this->attempts($key) >= $maxAttempts) {
            if ($this->redis->exists($key . ':timer')) {
                return true;
            }

            $this->resetAttempts($key);
        }

        return false;
    }

    /**
     * Increment the counter for a given key for a given decay time.
     *
     * @param string $key
     * @param int $decaySeconds
     * @return int
     */
    public function hit(string $key, int $decaySeconds = 60): int
    {
        $key = $this->prefix . $key;

        $this->redis->incr($key);
        $this->redis->expire($key, $decaySeconds);

        $this->redis->set($key . ':timer', time() + $decaySeconds, ['EX' => $decaySeconds]);

        return (int) $this->redis->get($key);
    }

    /**
     * Get the number of attempts for the given key.
     *
     * @param string $key
     * @return int
     */
    public function attempts(string $key): int
    {
        $key = $this->prefix . $key;

        return (int) $this->redis->get($key) ?: 0;
    }

    /**
     * Reset the number of attempts for the given key.
     *
     * @param string $key
     * @return bool
     */
    public function resetAttempts(string $key): bool
    {
        $key = $this->prefix . $key;

        return (bool) $this->redis->del($key, $key . ':timer');
    }

    /**
     * Get the number of seconds until the "key" is accessible again.
     *
     * @param string $key
     * @return int
     */
    public function availableIn(string $key): int
    {
        $key = $this->prefix . $key;

        $timer = $this->redis->get($key . ':timer');

        return $timer ? max(0, $timer - time()) : 0;
    }

    /**
     * Clear all rate limiting data for the given key.
     *
     * @param string $key
     * @return void
     */
    public function clear(string $key): void
    {
        $this->resetAttempts($key);
    }

    /**
     * Get the number of retries left for the given key.
     *
     * @param string $key
     * @param int $maxAttempts
     * @return int
     */
    public function retriesLeft(string $key, int $maxAttempts): int
    {
        return max(0, $maxAttempts - $this->attempts($key));
    }
}
