<?php

namespace Core\Tasking;

use Core\Contracts\Task\Task;
use Monolog\LogRecord;
use Psr\Log\LoggerInterface;

/**
 * A task responsible for writing a log record in a task worker.
 * It carries the full LogRecord object to preserve all context.
 */
class LogTask implements Task
{
    /**
     * Create a new task instance.
     *
     * @param LogRecord $record The log record to be processed.
     */
    public function __construct(public LogRecord $record)
    {
    }

    /**
     * Handle the task. This method is executed in a task worker.
     */
    public function handle(): bool
    {
        error_log('[DEBUG_LOG] LogTask: handle() called.');

        /** @var LoggerInterface $logger */
        $logger = app('log.task_writer'); // This resolves to a Monolog\Logger instance

        if (!$logger) {
            error_log('[DEBUG_LOG] LogTask: FAILED to resolve "log.task_writer" from container.');
            return false;
        }

        error_log('[DEBUG_LOG] LogTask: "log.task_writer" resolved. Calling log().');
        $logger->log($this->record->level, $this->record->message, $this->record->context);
        return true;
    }
}
