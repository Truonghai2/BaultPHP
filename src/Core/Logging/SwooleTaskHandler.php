<?php

namespace Core\Logging;

use Core\Tasking\LogTask;
use Core\Tasking\TaskService;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * A Monolog handler that offloads log writing to a Swoole Task Worker.
 */
class SwooleTaskHandler extends AbstractProcessingHandler
{
    public function __construct(private TaskService $taskService, int|string|Level $level = Level::Debug, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
    }

    /**
     * Instead of writing to a stream, this method dispatches a LogTask.
     */
    protected function write(LogRecord $record): void
    {
        $task = new LogTask(
            $record->level->getName(),
            $record->message,
            $record->context,
            $record->extra,
        );

        $this->taskService->dispatch($task);
    }
}
