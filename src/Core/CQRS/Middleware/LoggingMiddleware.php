<?php

namespace Core\CQRS\Middleware;

use Core\CQRS\Contracts\CommandInterface;
use Core\CQRS\Contracts\QueryInterface;
use Core\Support\Facades\Log;

/**
 * Logging Middleware
 *
 * Logs all commands and queries for audit trail.
 */
class LoggingMiddleware implements MiddlewareInterface
{
    public function handle(CommandInterface|QueryInterface $message, callable $next): mixed
    {
        $messageName = $message instanceof CommandInterface
            ? $message->getCommandName()
            : $message->getQueryName();

        $type = $message instanceof CommandInterface ? 'Command' : 'Query';

        Log::info("{$type} executing: {$messageName}", [
            'message' => $messageName,
            'type' => $type,
        ]);

        $startTime = microtime(true);

        try {
            $result = $next($message);

            $duration = (microtime(true) - $startTime) * 1000;

            Log::info("{$type} completed: {$messageName}", [
                'message' => $messageName,
                'duration_ms' => $duration,
            ]);

            return $result;
        } catch (\Throwable $e) {
            Log::error("{$type} failed: {$messageName}", [
                'message' => $messageName,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
