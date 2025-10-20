<?php

namespace Core\Tasking;

use Core\Application;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

/**
 * Handler để xử lý các task bất đồng bộ với coroutine
 */
class AsyncTaskHandler
{
    protected LoggerInterface $logger;
    protected Application $app;
    protected Channel $taskChannel;
    protected int $maxConcurrentTasks;

    public function __construct(Application $app, LoggerInterface $logger, int $maxConcurrentTasks = 10)
    {
        $this->app = $app;
        $this->logger = $logger;
        $this->maxConcurrentTasks = $maxConcurrentTasks;
        $this->taskChannel = new Channel($maxConcurrentTasks);
    }

    /**
     * Dispatch một task bất đồng bộ
     */
    public function dispatchAsync(callable $task, array $args = []): void
    {
        Coroutine::create(function () use ($task, $args) {
            try {
                $this->taskChannel->push(true); // Acquire slot

                $startTime = microtime(true);
                $result = call_user_func_array($task, $args);
                $duration = (microtime(true) - $startTime) * 1000;

                $this->logger->debug('Async task completed', [
                    'duration_ms' => round($duration, 2),
                    'result_type' => gettype($result),
                ]);

            } catch (\Throwable $e) {
                $this->logger->error('Async task failed', [
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            } finally {
                $this->taskChannel->pop(); // Release slot
            }
        });
    }

    /**
     * Dispatch multiple tasks concurrently
     */
    public function dispatchConcurrent(array $tasks): array
    {
        $results = [];
        $coroutines = [];

        foreach ($tasks as $key => $task) {
            $coroutines[$key] = Coroutine::create(function () use ($task, &$results, $key) {
                try {
                    $startTime = microtime(true);
                    $result = call_user_func_array($task['callback'], $task['args'] ?? []);
                    $duration = (microtime(true) - $startTime) * 1000;

                    $results[$key] = [
                        'success' => true,
                        'result' => $result,
                        'duration_ms' => round($duration, 2),
                    ];
                } catch (\Throwable $e) {
                    $results[$key] = [
                        'success' => false,
                        'error' => $e->getMessage(),
                        'duration_ms' => 0,
                    ];
                }
            });
        }

        // Wait for all coroutines to complete
        foreach ($coroutines as $coroutine) {
            Coroutine::join([$coroutine]);
        }

        return $results;
    }

    /**
     * Get current task queue status
     */
    public function getStatus(): array
    {
        return [
            'max_concurrent' => $this->maxConcurrentTasks,
            'available_slots' => $this->taskChannel->stats()['queue_num'] ?? 0,
            'active_tasks' => $this->maxConcurrentTasks - ($this->taskChannel->stats()['queue_num'] ?? 0),
        ];
    }
}
