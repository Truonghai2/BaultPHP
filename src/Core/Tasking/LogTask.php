<?php

namespace Core\Tasking;

use Core\Contracts\Task\Task;

/**
 * A task responsible for writing a log entry.
 * This task is dispatched to a Swoole Task Worker to make logging asynchronous.
 */
class LogTask implements Task
{
    public function __construct(
        private string $level,
        private string $message,
        private array $context = [],
        private array $extra = [],
    ) {
    }

    public function handle(): void
    {
        app('log.sync')->log($this->level, $this->message, $this->context);
    }
}
