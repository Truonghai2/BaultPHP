<?php

namespace Core\Debug\Proxy;

use Core\Debug\DebugManager;
use Redis;

/**
 * A proxy for Redis connections that intercepts commands to log them for debugging.
 */
class DebugRedisProxy
{
    private DebugManager $debugManager;

    public function __construct(private Redis $redis, DebugManager $debugManager)
    {
        $this->debugManager = $debugManager;
    }

    /**
     * Intercept all calls, log them, and forward to the original Redis object.
     */
    public function __call(string $command, array $arguments): mixed
    {
        $startTime = microtime(true);
        try {
            return $this->redis->{$command}(...$arguments);
        } finally {
            $duration = microtime(true) - $startTime;
            $this->debugManager->recordRedisCommand($command, $arguments, $duration);
        }
    }

    /**
     * Provide access to the original Redis connection for specific checks.
     * @return Redis
     */
    public function getOriginalConnection(): Redis
    {
        return $this->redis;
    }
}
