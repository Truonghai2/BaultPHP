<?php

namespace Core\Logging;

use Core\Contracts\Task\TaskDispatcher;
use Core\Tasking\LogTask;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * A Monolog handler that offloads log writing to a Swoole Task Worker.
 * This prevents I/O blocking in the main HTTP workers.
 */
class SwooleTaskHandler extends AbstractProcessingHandler
{
    public function __construct(private TaskDispatcher $taskService, int|string|Level $level = Level::Debug, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
    }

    /**
     * Instead of writing to a stream, this method dispatches a LogTask.
     */
    protected function write(LogRecord $record): void
    {
        // DEBUG: Kiểm tra xem handler này có được gọi không.
        error_log('[DEBUG_LOG] SwooleTaskHandler: write() called. Dispatching LogTask...');

        // We dispatch the entire LogRecord object.
        // It is serializable and contains all the necessary context.
        $task = new LogTask($record);
        $this->taskService->dispatch($task);
    }
}
