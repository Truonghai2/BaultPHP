<?php

declare(strict_types=1);

namespace Core\Server;

use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

/**
 * Limits the number of concurrent coroutines per request.
 * 
 * Prevents a single request from spawning too many coroutines
 * which could exhaust system resources.
 */
class CoroutineLimiter
{
    private Channel $channel;
    private int $maxCoroutines;
    private int $activeCoroutines = 0;

    /**
     * @param int $maxCoroutines Maximum concurrent coroutines allowed
     */
    public function __construct(int $maxCoroutines = 100)
    {
        $this->maxCoroutines = $maxCoroutines;
        $this->channel = new Channel($maxCoroutines);
        
        // Fill channel with tokens
        for ($i = 0; $i < $maxCoroutines; $i++) {
            $this->channel->push(true);
        }
    }

    /**
     * Execute a callable within coroutine limits.
     *
     * @param callable $callback
     * @param array $args
     * @return mixed
     * @throws \RuntimeException if coroutine limit is reached
     */
    public function run(callable $callback, array $args = []): mixed
    {
        // Try to acquire a slot
        $acquired = $this->channel->pop(1.0); // 1 second timeout
        
        if (!$acquired) {
            throw new \RuntimeException("Coroutine limit reached ({$this->maxCoroutines})");
        }

        $this->activeCoroutines++;
        
        try {
            $result = call_user_func_array($callback, $args);
            return $result;
        } finally {
            $this->channel->push(true); // Release slot
            $this->activeCoroutines--;
        }
    }

    /**
     * Execute multiple callables concurrently with limit.
     *
     * @param array $callbacks Array of callables
     * @return array Results from all callbacks
     */
    public function runConcurrent(array $callbacks): array
    {
        $results = [];
        $coroutines = [];

        foreach ($callbacks as $key => $callback) {
            // Acquire slot before creating coroutine
            $acquired = $this->channel->pop(1.0);
            
            if (!$acquired) {
                $results[$key] = [
                    'success' => false,
                    'error' => 'Coroutine limit reached',
                ];
                continue;
            }

            $this->activeCoroutines++;

            $coroutines[$key] = Coroutine::create(function() use ($callback, &$results, $key) {
                try {
                    $result = is_callable($callback) ? $callback() : null;
                    $results[$key] = [
                        'success' => true,
                        'result' => $result,
                    ];
                } catch (\Throwable $e) {
                    $results[$key] = [
                        'success' => false,
                        'error' => $e->getMessage(),
                    ];
                } finally {
                    $this->channel->push(true); // Release slot
                    $this->activeCoroutines--;
                }
            });
        }

        // Wait for all coroutines to complete
        if (!empty($coroutines)) {
            Coroutine::join($coroutines);
        }

        return $results;
    }

    /**
     * Get the number of active coroutines.
     *
     * @return int
     */
    public function getActiveCount(): int
    {
        return $this->activeCoroutines;
    }

    /**
     * Get the number of available slots.
     *
     * @return int
     */
    public function getAvailableCount(): int
    {
        return $this->channel->length();
    }

    /**
     * Get statistics about the limiter.
     *
     * @return array
     */
    public function getStats(): array
    {
        return [
            'max_coroutines' => $this->maxCoroutines,
            'active_coroutines' => $this->activeCoroutines,
            'available_slots' => $this->channel->length(),
            'utilization' => $this->maxCoroutines > 0 
                ? round($this->activeCoroutines / $this->maxCoroutines, 4) 
                : 0,
        ];
    }

    /**
     * Check if the limiter is at capacity.
     *
     * @return bool
     */
    public function isAtCapacity(): bool
    {
        return $this->channel->length() === 0;
    }
}
