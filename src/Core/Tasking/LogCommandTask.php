<?php

namespace Core\Tasking;

use Core\Contracts\Task\Task;
use Psr\Log\LoggerInterface;

class LogCommandTask implements Task
{
    public function __construct(
        private string $level,
        private string $message,
        private array $context = [],
    ) {
    }

    /**
     * The logic to be executed in a Swoole Task Worker.
     * It resolves the logger from the container and writes the log.
     */
    public function handle(): void
    {
        $logger = app(LoggerInterface::class);
        $logger->log($this->level, $this->message, $this->context);
    }
}
